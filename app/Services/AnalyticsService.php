<?php

declare(strict_types=1);

class AnalyticsService
{
    public function recordVisit(string $path, array $server, ?int $userId = null): void
    {
        try {
            $pdo = $this->pdo();

            if ($this->shouldSkipTracking($path, $server)) {
                return;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO site_visits (
                    session_id,
                    user_id,
                    visited_at,
                    path,
                    ip_address,
                    country_code,
                    region,
                    device_type,
                    os_family,
                    browser,
                    user_agent
                ) VALUES (:session_id, :user_id, NOW(), :path, :ip_address, :country_code, :region, :device_type, :os_family, :browser, :user_agent)'
            );

            $stmt->execute([
                'session_id' => $this->sessionId(),
                'user_id' => $userId,
                'path' => $this->normalizePath($path),
                'ip_address' => $this->extractIpAddress($server),
                'country_code' => $this->extractCountryCode($server),
                'region' => $this->extractRegion($server),
                'device_type' => $this->detectDeviceType($server),
                'os_family' => $this->detectOsFamily($server),
                'browser' => $this->detectBrowser($server),
                'user_agent' => $server['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable) {
            // Table site_visits might not exist in minimal database setup
        }
    }

    public function getDashboardStats(PDO $pdo): array
    {
        try {
            $totalVisits = (int) $pdo->query('SELECT COUNT(*) FROM site_visits')->fetchColumn();
            $uniqueSessions = (int) $pdo->query(
                'SELECT COUNT(*) FROM (SELECT DISTINCT session_id FROM site_visits WHERE session_id IS NOT NULL AND session_id <> "") AS s'
            )->fetchColumn();
            $uniqueUsers = (int) $pdo->query(
                'SELECT COUNT(*) FROM (SELECT DISTINCT user_id FROM site_visits WHERE user_id IS NOT NULL) AS u'
            )->fetchColumn();

            $currentClassVisitStats = $pdo->query(
                'SELECT ay.name AS academic_year_name,
                        c.id,
                        c.class_name,
                        c.class_code,
                        COUNT(DISTINCT cm.user_id) AS total_students,
                        COUNT(sv.id) AS page_visits,
                        COUNT(DISTINCT sv.user_id) AS students_with_visits,
                        COUNT(DISTINCT cm.user_id) - COUNT(DISTINCT sv.user_id) AS students_without_visits
                 FROM academic_years ay
                 INNER JOIN classes c ON c.academic_year_id = ay.id
                 LEFT JOIN class_members cm ON cm.class_id = c.id
                 LEFT JOIN site_visits sv ON sv.user_id = cm.user_id
                    AND sv.visited_at >= STR_TO_DATE(CONCAT(ay.start_year, "-09-01"), "%Y-%m-%d")
                    AND sv.visited_at < STR_TO_DATE(CONCAT(ay.end_year, "-09-01"), "%Y-%m-%d")
                 WHERE ay.is_current = 1
                 GROUP BY ay.id, ay.name, c.id, c.class_name, c.class_code
                 ORDER BY c.class_code'
            )->fetchAll(PDO::FETCH_ASSOC);

            $historicalAcademicYearVisitStats = $pdo->query(
                'SELECT ay.name AS academic_year_name,
                        COUNT(DISTINCT membership.user_id) AS total_students,
                        COUNT(sv.id) AS page_visits,
                        COUNT(DISTINCT sv.user_id) AS students_with_visits,
                        COUNT(DISTINCT membership.user_id) - COUNT(DISTINCT sv.user_id) AS students_without_visits
                 FROM academic_years ay
                 LEFT JOIN (
                      SELECT DISTINCT h.user_id, hc.academic_year_id
                      FROM class_member_history h
                      INNER JOIN classes hc ON hc.id = h.to_class_id
                     WHERE h.to_class_id IS NOT NULL
                     UNION
                     SELECT DISTINCT cm.user_id, c.academic_year_id
                     FROM class_members cm
                     INNER JOIN classes c ON c.id = cm.class_id
                 ) membership ON membership.academic_year_id = ay.id
                 LEFT JOIN site_visits sv ON sv.user_id = membership.user_id
                    AND sv.visited_at >= STR_TO_DATE(CONCAT(ay.start_year, "-09-01"), "%Y-%m-%d")
                    AND sv.visited_at < STR_TO_DATE(CONCAT(ay.end_year, "-09-01"), "%Y-%m-%d")
                 WHERE ay.start_year < (SELECT start_year FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1)
                 GROUP BY ay.id, ay.name, ay.start_year
                 HAVING total_students > 0
                 ORDER BY ay.start_year DESC'
            )->fetchAll(PDO::FETCH_ASSOC);

            $deviceStats = $pdo->query(
                'SELECT device_type, COUNT(*) AS total
                 FROM site_visits
                 WHERE device_type IS NOT NULL AND device_type <> ""
                 GROUP BY device_type
                 ORDER BY total DESC'
            )->fetchAll(PDO::FETCH_ASSOC);

            $osStats = $pdo->query(
                'SELECT os_family, COUNT(*) AS total
                 FROM site_visits
                 WHERE os_family IS NOT NULL AND os_family <> ""
                 GROUP BY os_family
                 ORDER BY total DESC'
            )->fetchAll(PDO::FETCH_ASSOC);

            $geoStats = $pdo->query(
                'SELECT COALESCE(country_code, "Desconegut") AS country_code,
                        COALESCE(region, "Desconegut") AS region,
                        COUNT(*) AS total
                 FROM site_visits
                 GROUP BY country_code, region
                 ORDER BY total DESC
                 LIMIT 10'
            )->fetchAll(PDO::FETCH_ASSOC);

            $recentVisits = $pdo->query(
                'SELECT sv.visited_at, sv.path, sv.country_code, sv.region, sv.device_type, sv.os_family, sv.browser,
                        u.name, u.surname
                 FROM site_visits sv
                 LEFT JOIN users u ON u.id = sv.user_id
                 ORDER BY sv.visited_at DESC
                 LIMIT 10'
            )->fetchAll(PDO::FETCH_ASSOC);

            $pageStats = $pdo->query(
                'SELECT path, COUNT(*) AS total
                 FROM site_visits
                 GROUP BY path
                 ORDER BY total DESC
                 LIMIT 10'
            )->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total_visits' => $totalVisits,
                'unique_sessions' => $uniqueSessions,
                'unique_users' => $uniqueUsers,
                'class_stats' => $currentClassVisitStats,
                'current_class_visit_stats' => $currentClassVisitStats,
                'historical_academic_year_visit_stats' => $historicalAcademicYearVisitStats,
                'device_stats' => $deviceStats,
                'os_stats' => $osStats,
                'geo_stats' => $geoStats,
                'recent_visits' => $recentVisits,
                'page_stats' => $pageStats,
            ];
        } catch (Throwable) {
            return [
                'total_visits' => 0,
                'unique_sessions' => 0,
                'unique_users' => 0,
                'class_stats' => [],
                'current_class_visit_stats' => [],
                'historical_academic_year_visit_stats' => [],
                'device_stats' => [],
                'os_stats' => [],
                'geo_stats' => [],
                'recent_visits' => [],
                'page_stats' => [],
            ];
        }
    }

    private function shouldSkipTracking(string $path, array $server): bool
    {
        if ($path === '' || $path === '/') {
            return false;
        }

        if ($path === '/robots.txt' || $path === '/favicon.ico') {
            return true;
        }

        $userAgent = (string) ($server['HTTP_USER_AGENT'] ?? '');

        return preg_match('/bot|crawl|spider|slurp|curl|wget/i', $userAgent) === 1;
    }

    private function sessionId(): string
    {
        startAppSession();

        return (string) session_id();
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }

    private function extractIpAddress(array $server): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            $value = $server[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $parts = explode(',', $value);
                return trim((string) $parts[0]);
            }
        }

        return null;
    }

    private function extractCountryCode(array $server): ?string
    {
        foreach (['HTTP_CF_IPCOUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE'] as $key) {
            $value = $server[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return strtoupper($value);
            }
        }

        return null;
    }

    private function extractRegion(array $server): ?string
    {
        foreach (['HTTP_CF_REGION', 'GEOIP_REGION', 'HTTP_X_REGION'] as $key) {
            $value = $server[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function detectDeviceType(array $server): string
    {
        $ua = strtolower((string) ($server['HTTP_USER_AGENT'] ?? ''));

        if (preg_match('/ipad|tablet/i', $ua) === 1) {
            return 'tablet';
        }

        if (preg_match('/mobile|android|iphone|ipod/i', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function detectOsFamily(array $server): string
    {
        $ua = strtolower((string) ($server['HTTP_USER_AGENT'] ?? ''));

        if (preg_match('/android/i', $ua) === 1) {
            return 'android';
        }

        if (preg_match('/iphone|ipad|ipod/i', $ua) === 1) {
            return 'ios';
        }

        if (preg_match('/mac os|macintosh/i', $ua) === 1) {
            return 'macos';
        }

        if (preg_match('/windows|win32|win64/i', $ua) === 1) {
            return 'windows';
        }

        if (preg_match('/linux/i', $ua) === 1) {
            return 'linux';
        }

        return 'unknown';
    }

    private function detectBrowser(array $server): string
    {
        $ua = strtolower((string) ($server['HTTP_USER_AGENT'] ?? ''));

        if (preg_match('/edg\//i', $ua) === 1) {
            return 'edge';
        }

        if (preg_match('/chrome/i', $ua) === 1) {
            return 'chrome';
        }

        if (preg_match('/firefox/i', $ua) === 1) {
            return 'firefox';
        }

        if (preg_match('/safari/i', $ua) === 1) {
            return 'safari';
        }

        if (preg_match('/opr\//i', $ua) === 1) {
            return 'opera';
        }

        return 'unknown';
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
