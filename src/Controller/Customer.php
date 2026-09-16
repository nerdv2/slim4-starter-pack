<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\DTO\Request\CustomerCreateRequest;
use App\DTO\Request\CustomerListRequest;
use App\DTO\Request\CustomerUpdateRequest;
use App\Exceptions\AppException;
use App\Helper\JsonResponse;
use App\Service\CustomerService;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

final class Customer extends BaseController
{
    private CustomerService $customerService;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->customerService = $container->get('customerService');
    }

    #[OA\Get(
        path: '/customer',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Paginated customer list with keyword search and status filter.',
        summary: 'List customers',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'keywords', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                description: 'lead, prospect, active or inactive',
                schema: new OA\Schema(type: 'string')
            ),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function list(Request $request, Response $response): Response
    {
        $dto = CustomerListRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        $result = $this->customerService->list($dto->keywords, $dto->status, $dto->page, $dto->limit);

        return JsonResponse::success($response, $result['data'], 'Customers retrieved.', [
            'total_page' => $result['total_page'],
            'total_data' => $result['total_data'],
        ]);
    }

    #[OA\Get(
        path: '/customer/stats',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Customer counts per status, total and creations in the last seven days.',
        summary: 'Customer statistics',
        security: [['auth_token' => []]]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function stats(Request $request, Response $response): Response
    {
        return JsonResponse::success($response, $this->customerService->stats());
    }

    #[OA\Get(
        path: '/customer/{id}',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Retrieve a single customer.',
        summary: 'Customer detail',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $customer = $this->customerService->get($this->idFromArgs($args));
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $customer);
    }

    #[OA\Post(
        path: '/customer',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Create a customer. Requires an admin token.',
        summary: 'Create customer',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'company', type: 'string'),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            enum: ['lead', 'prospect', 'active', 'inactive']
                        ),
                        new OA\Property(property: 'address', type: 'string'),
                        new OA\Property(property: 'notes', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Customer created')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    public function create(Request $request, Response $response): Response
    {
        $dto = CustomerCreateRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $customer = $this->customerService->create($dto);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $customer, 'Customer created.', [], HttpStatus::CREATED);
    }

    #[OA\Put(
        path: '/customer/{id}',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Update a customer. Requires an admin token.',
        summary: 'Update customer',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'company', type: 'string'),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            enum: ['lead', 'prospect', 'active', 'inactive']
                        ),
                        new OA\Property(property: 'address', type: 'string'),
                        new OA\Property(property: 'notes', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Customer updated')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function update(Request $request, Response $response, array $args): Response
    {
        $dto = CustomerUpdateRequest::fromRequest($request, $args['id'] ?? null);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $customer = $this->customerService->update($dto);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $customer, 'Customer updated.');
    }

    #[OA\Delete(
        path: '/customer/{id}',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Soft-delete a customer and its avatar. Requires an admin token.',
        summary: 'Delete customer',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Customer deleted')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->customerService->delete($this->idFromArgs($args));
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, [], 'Customer deleted.');
    }

    #[OA\Post(
        path: '/customer/{id}/avatar',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Upload or replace the customer avatar (JPEG, PNG or WebP). Requires an admin token.',
        summary: 'Upload avatar',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['avatar'],
                    properties: [
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Avatar stored')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function uploadAvatar(Request $request, Response $response, array $args): Response
    {
        $files = $request->getUploadedFiles();
        $avatar = $files['avatar'] ?? null;
        if (!$avatar instanceof UploadedFileInterface) {
            return $this->validationError($response, ['avatar' => 'An avatar file is required.']);
        }

        try {
            $customer = $this->customerService->uploadAvatar($this->idFromArgs($args), $avatar);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $customer, 'Avatar updated.');
    }

    #[OA\Delete(
        path: '/customer/{id}/avatar',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Remove the customer avatar. Requires an admin token.',
        summary: 'Remove avatar',
        security: [['auth_token' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Avatar removed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function removeAvatar(Request $request, Response $response, array $args): Response
    {
        try {
            $customer = $this->customerService->removeAvatar($this->idFromArgs($args));
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $customer, 'Avatar removed.');
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
