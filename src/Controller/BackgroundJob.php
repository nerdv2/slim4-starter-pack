<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\Helper\JsonResponse;
use Oeltima\SimpleQueue\Contract\JobData;
use Oeltima\SimpleQueue\JobDispatcher;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BackgroundJob extends BaseController
{
    private JobDispatcher $jobDispatcher;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->jobDispatcher = $container->get('jobDispatcher');
    }

    #[OA\Post(
        path: '/admin/background-jobs/example',
        tags: [OpenApiTags::DIAGNOSTIC],
        description: 'Dispatch the example background job. Requires an admin token.',
        summary: 'Dispatch example job',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            description: 'Message echoed by the job',
                            type: 'string'
                        ),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Job queued')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    public function dispatchExample(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $message = trim((string) ($body['message'] ?? ''));

        $payload = $message === '' ? [] : ['message' => $message];
        $jobId = $this->jobDispatcher->dispatch('example.hello', $payload);

        return JsonResponse::success($response, [
            'job_id' => $jobId,
            'status' => $this->jobDispatcher->getStatus($jobId)?->status->value,
        ], 'Job queued.');
    }

    #[OA\Get(
        path: '/admin/background-jobs/{id}',
        tags: [OpenApiTags::DIAGNOSTIC],
        description: 'Retrieve a background job status. Requires an admin token.',
        summary: 'Background job status',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 404, description: 'Job not found')]
    public function status(Request $request, Response $response, array $args): Response
    {
        $jobId = (int) ($args['id'] ?? 0);
        $job = $jobId > 0 ? $this->jobDispatcher->getStatus($jobId) : null;
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
        return [
            'id' => $job->id,
            'queue' => $job->queue,
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
    }
}
