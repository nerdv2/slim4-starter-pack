<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\Helper\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class Health extends BaseController
{
    #[OA\Get(
        path: '/health',
        tags: [OpenApiTags::DIAGNOSTIC],
        description: 'Liveness probe: returns 200 while the application is running.',
        summary: 'Liveness probe'
    )]
    #[OA\Response(response: 200, description: 'Application is running')]
    public function liveness(Request $request, Response $response): Response
    {
        return JsonResponse::withJson($response, [
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    #[OA\Get(
        path: '/health/ready',
        tags: [OpenApiTags::DIAGNOSTIC],
        description: 'Readiness probe: verifies database connectivity.',
        summary: 'Readiness probe'
    )]
    #[OA\Response(response: 200, description: 'Application is ready')]
    #[OA\Response(response: 503, description: 'Service degraded or unavailable')]
    public function readiness(Request $request, Response $response): Response
    {
        $checks = ['database' => $this->checkDatabase()];
        $healthy = !in_array(false, array_column($checks, 'healthy'), true);

        return JsonResponse::withJson($response, [
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => date(DATE_ATOM),
            'checks' => $checks,
        ], $healthy ? HttpStatus::OK : HttpStatus::SERVICE_UNAVAILABLE);
    }

    #[OA\Get(
        path: '/health/detailed',
        tags: [OpenApiTags::DIAGNOSTIC],
        description: 'Detailed health status (database, storage, memory). Requires X-Health-Token.',
        summary: 'Detailed health check',
        security: [['health_token' => []]]
    )]
    #[OA\Response(response: 200, description: 'Detailed health status')]
    #[OA\Response(response: 401, description: 'Token missing or invalid')]
    #[OA\Response(response: 503, description: 'Service degraded or diagnostics not configured')]
    public function detailed(Request $request, Response $response): Response
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
        ];
        $healthy = !in_array(false, array_column($checks, 'healthy'), true);

        return JsonResponse::withJson($response, [
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => date(DATE_ATOM),
            'environment' => (string) ($_SERVER['APP_ENVIRONMENT'] ?? 'production'),
            'php' => PHP_VERSION,
            'memory_usage_bytes' => memory_get_usage(true),
            'checks' => $checks,
        ], $healthy ? HttpStatus::OK : HttpStatus::SERVICE_UNAVAILABLE);
    }

    /**
     * @return array{healthy: bool, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $this->db()->query('SELECT 1')->first();

            return ['healthy' => true];
        } catch (Throwable $exception) {
            return ['healthy' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array{healthy: bool, path: string}
     */
    private function checkStorage(): array
    {
        $path = dirname(__DIR__, 2) . '/storage';

        return ['healthy' => is_dir($path) && is_writable($path), 'path' => $path];
    }
}
