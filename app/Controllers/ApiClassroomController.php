<?php

declare(strict_types=1);

class ApiClassroomController
{
    public function __construct(private ClassroomWebhookService $webhookService)
    {
    }

    public function webhook(): string
    {
        if (!$this->hasValidBearerToken()) {
            return $this->jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $rawInput = file_get_contents('php://input');
        if (!is_string($rawInput) || trim($rawInput) === '') {
            return $this->jsonResponse(['ok' => false, 'error' => 'Empty JSON payload'], 400);
        }

        try {
            $payload = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                return $this->jsonResponse(['ok' => false, 'error' => 'Invalid JSON object'], 400);
            }

            $result = $this->webhookService->storePayload($payload, [
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (JsonException) {
            return $this->jsonResponse(['ok' => false, 'error' => 'Malformed JSON'], 400);
        } catch (Throwable $throwable) {
            (new LogService())->write('api_classroom_webhook_failed error=' . str_replace(["\r", "\n"], ' ', $throwable->getMessage()));

            return $this->jsonResponse(['ok' => false, 'error' => 'Internal server error'], 500);
        }

        return $this->jsonResponse([
            'ok' => true,
            'run_id' => (int) $result['run_id'],
            'received_rows' => (int) $result['received_rows'],
        ]);
    }

    private function hasValidBearerToken(): bool
    {
        $expectedToken = trim((string) env('CLASSROOM_WEBHOOK_TOKEN', ''));
        if ($expectedToken === '') {
            return false;
        }

        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authorization === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authorization = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }

        if (!str_starts_with($authorization, 'Bearer ')) {
            return false;
        }

        $providedToken = trim(substr($authorization, strlen('Bearer ')));

        return $providedToken !== '' && hash_equals($expectedToken, $providedToken);
    }

    private function jsonResponse(array $payload, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
