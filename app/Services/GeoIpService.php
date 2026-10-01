<?php

declare(strict_types=1);

class GeoIpService
{
    private const ENDPOINT = 'https://ipwho.is/';
    private const CACHE_TTL = 2592000;
    private const FAILURE_CACHE_TTL = 3600;

    public function lookup(?string $ipAddress): ?array
    {
        if (!$this->isEnabled() || !$this->isPublicIp($ipAddress)) {
            return null;
        }

        $cached = $this->readCache($ipAddress);
        if ($cached !== null) {
            return ($cached['resolved'] ?? false) === true && is_array($cached['data'] ?? null)
                ? $cached['data']
                : null;
        }

        $payload = $this->request(self::ENDPOINT . rawurlencode($ipAddress));
        if (!is_array($payload) || ($payload['success'] ?? false) !== true) {
            $this->writeCache($ipAddress, ['resolved' => false]);
            return null;
        }

        $countryCode = strtoupper(trim((string) ($payload['country_code'] ?? '')));
        $latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1 || $latitude === false || $longitude === false) {
            $this->writeCache($ipAddress, ['resolved' => false]);
            return null;
        }

        $result = [
            'country_code' => $countryCode,
            'region' => $this->normalizeRegion($payload['region'] ?? null),
            'latitude' => max(-90.0, min(90.0, (float) $latitude)),
            'longitude' => max(-180.0, min(180.0, (float) $longitude)),
        ];

        $this->writeCache($ipAddress, ['resolved' => true, 'data' => $result]);

        return $result;
    }

    private function isEnabled(): bool
    {
        return filter_var(env('GEOIP_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function isPublicIp(?string $ipAddress): bool
    {
        if ($ipAddress === null || $ipAddress === '') {
            return false;
        }

        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function request(string $url): ?array
    {
        $body = function_exists('curl_init')
            ? $this->requestWithCurl($url)
            : $this->requestWithStreams($url);

        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function requestWithCurl(string $url): ?string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Mediterrani analytics/1.0',
        ];
        $caBundle = $this->caBundlePath();
        if ($caBundle !== null) {
            $options[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return is_string($body) && $status >= 200 && $status < 300 ? $body : null;
    }

    private function requestWithStreams(string $url): ?string
    {
        $sslOptions = ['verify_peer' => true, 'verify_peer_name' => true];
        $caBundle = $this->caBundlePath();
        if ($caBundle !== null) {
            $sslOptions['cafile'] = $caBundle;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => false,
                'header' => "User-Agent: Mediterrani analytics/1.0\r\n",
            ],
            'ssl' => $sslOptions,
        ]);
        $body = @file_get_contents($url, false, $context);

        return is_string($body) ? $body : null;
    }

    private function caBundlePath(): ?string
    {
        $configured = trim((string) env('GEOIP_CA_BUNDLE_PATH', 'storage/certs/cacert.pem'));
        if ($configured === '') {
            return null;
        }

        $path = str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configured) === 1
            ? $configured
            : dirname(__DIR__, 2) . '/' . ltrim($configured, '/\\');

        return is_file($path) ? $path : null;
    }

    private function normalizeRegion(mixed $region): ?string
    {
        if (!is_string($region)) {
            return null;
        }

        $region = trim($region);
        if ($region === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($region, 0, 100) : substr($region, 0, 100);
    }

    private function cacheFile(string $ipAddress): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/geoip/' . hash('sha256', $ipAddress) . '.json';
    }

    private function readCache(string $ipAddress): ?array
    {
        $file = $this->cacheFile($ipAddress);
        if (!is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($file), true);
        if (!is_array($decoded)) {
            return null;
        }

        $ttl = ($decoded['resolved'] ?? false) === true ? self::CACHE_TTL : self::FAILURE_CACHE_TTL;
        if (filemtime($file) < time() - $ttl) {
            return null;
        }

        return $decoded;
    }

    private function writeCache(string $ipAddress, array $data): void
    {
        $file = $this->cacheFile($ipAddress);
        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
