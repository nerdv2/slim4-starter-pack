<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\Helper\JsonResponse;
use Oeltima\SimpleQueue\Contract\JobData;
use Oeltima\SimpleQueue\Contract\JobStatus;
use Oeltima\SimpleQueue\JobDispatcher;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Generic background-job status. Exports and imports report progress and
 * results through this endpoint; the job payload stays private.
 */
final class Job extends BaseController
{
    private JobDispatcher $jobDispatcher;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->jobDispatcher = $container->get('jobDispatcher');
    }

    #[OA\Get(
        path: '/jobs/{id}',
        tags: [OpenApiTags::JOB],
        description: 'Status, progress and result of a background job.',
        summary: 'Job status',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 404, description: 'Job not found')]
    public function status(Request $request, Response $response, array $args): Response
    {
        $jobId = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT);
        $job = is_int($jobId) && $jobId > 0 ? $this->jobDispatcher->getStatus($jobId) : null;
        if ($job === null) {
            return JsonResponse::error($response, 'Job not found.', [], [], HttpStatus::NOT_FOUND);
        }

        return JsonResponse::success($response, $this->toArray($job));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(JobData $job): array
    {
        $data = [
            'id' => $job->id,
            'type' => $job->type,
            'status' => $job->status->value,
            'attempts' => $job->attempts,
            'max_attempts' => $job->maxAttempts,
            'progress' => $job->progress,
            'progress_message' => $job->progressMessage,
            'result' => $job->result,
            'error_message' => $job->errorMessage,
            'created_at' => $job->createdAt,
            'started_at' => $job->startedAt,
            'completed_at' => $job->completedAt,
        ];

        if ($job->type === 'customer.export' && $job->status === JobStatus::Completed) {
            $data['download_url'] = rtrim((string) (
                $_SERVER['APP_BASE_URL'] ?? $_ENV['APP_BASE_URL'] ?? ''
            ), '/') . '/customer/export/' . $job->id . '/download';
        }

        return $data;
    }
}
