<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\DTO\Request\CustomerExportRequest;
use App\Exceptions\AppException;
use App\Helper\JsonResponse;
use App\Service\CustomerTransferService;
use Oeltima\SimpleQueue\Contract\JobStatus;
use Oeltima\SimpleQueue\JobDispatcher;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Stream;

final class CustomerTransfer extends BaseController
{
    private CustomerTransferService $transferService;

    private JobDispatcher $jobDispatcher;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->transferService = $container->get('customerTransferService');
        $this->jobDispatcher = $container->get('jobDispatcher');
    }

    #[OA\Post(
        path: '/customer/export',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Queue a CSV export of the filtered customer list and return the background job id.',
        summary: 'Queue customer export',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'keywords', type: 'string'),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            enum: ['lead', 'prospect', 'active', 'inactive']
                        ),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 202, description: 'Export queued')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function export(Request $request, Response $response): Response
    {
        $authUser = $this->user($request);
        if ($authUser === null) {
            return JsonResponse::error($response, 'Authentication required.', [], [], HttpStatus::UNAUTHORIZED);
        }

        $dto = CustomerExportRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $result = $this->transferService->requestExport($dto, (int) $authUser->id);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $result, 'Export queued.', [], HttpStatus::ACCEPTED);
    }

    #[OA\Post(
        path: '/customer/import',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Upload a customer CSV and queue the import job. Requires an admin token.',
        summary: 'Queue customer import',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            description: 'CSV with a name column; email, phone, company, status, address and notes are optional',
                            type: 'string',
                            format: 'binary'
                        ),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 202, description: 'Import queued')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    public function import(Request $request, Response $response): Response
    {
        $authUser = $this->user($request);
        if ($authUser === null) {
            return JsonResponse::error($response, 'Authentication required.', [], [], HttpStatus::UNAUTHORIZED);
        }

        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->validationError($response, ['file' => 'A CSV file is required.']);
        }

        try {
            $result = $this->transferService->requestImport($file, (int) $authUser->id);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $result, 'Import queued.', [], HttpStatus::ACCEPTED);
    }

    #[OA\Get(
        path: '/customer/export/{id}/download',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Download a completed export as a CSV attachment.',
        summary: 'Download customer export',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'CSV file')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 404, description: 'Export not found')]
    #[OA\Response(response: 409, description: 'Export is not completed yet')]
    public function download(Request $request, Response $response, array $args): Response
    {
        $jobId = $this->idFromArgs($args);
        $job = $jobId > 0 ? $this->jobDispatcher->getStatus($jobId) : null;
        if ($job === null || $job->type !== 'customer.export') {
            return JsonResponse::error($response, 'Export not found.', [], [], HttpStatus::NOT_FOUND);
        }

        if ($job->status !== JobStatus::Completed) {
            return JsonResponse::error(
                $response,
                'Export is not completed yet.',
                ['status' => $job->status->value],
                [],
                HttpStatus::CONFLICT
            );
        }

        $path = $this->transferService->exportFilePath($jobId);
        if (!is_file($path)) {
            return JsonResponse::error(
                $response,
                'The export file is no longer available.',
                [],
                [],
                HttpStatus::NOT_FOUND
            );
        }

        $size = filesize($path);
        $stream = new Stream(fopen($path, 'rb'));

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Content-Length', (string) ($size === false ? 0 : $size))
            ->withBody($stream);
    }

    /**
     * @param array<string, string> $args
     */
    private function idFromArgs(array $args): int
    {
        $id = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? $id : 0;
    }
}
