<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\DTO\Request\CustomerIdRequest;
use App\DTO\Request\CustomerRequest;
use App\DTO\Request\CustomerUpdateRequest;
use App\Exceptions\AppException;
use App\Helper\JsonResponse;
use App\Helper\Pagination;
use App\Service\CustomerService;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
        description: 'Retrieve a paginated customer list with optional keyword search.',
        summary: 'List customers',
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number (1-based)',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Rows per page (max 100)',
                schema: new OA\Schema(type: 'integer', default: 20)
            ),
            new OA\Parameter(
                name: 'keywords',
                in: 'query',
                description: 'Case-insensitive name search',
                schema: new OA\Schema(type: 'string', default: '')
            ),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    public function get(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        [$page, $limit] = Pagination::sanitize($query['page'] ?? null, $query['limit'] ?? null);
        $keywords = trim((string) ($query['keywords'] ?? ''));

        $result = $this->customerService->list($keywords, $page, $limit);

        return JsonResponse::success($response, $result['data'], JsonResponse::DEFAULT_SUCCESS_MESSAGE, [
            'total_page' => $result['total_page'],
            'total_data' => $result['total_data'],
        ]);
    }

    #[OA\Post(
        path: '/customer/add',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Create a customer. Requires an admin token.',
        summary: 'Create customer',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', description: 'Customer name', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    public function add(Request $request, Response $response): Response
    {
        $dto = CustomerRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $this->customerService->create($dto->name);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, [], 'Customer created.');
    }

    #[OA\Post(
        path: '/customer/update',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Rename a customer. Requires an admin token.',
        summary: 'Update customer',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id', 'name'],
                    properties: [
                        new OA\Property(property: 'id', description: 'Customer id', type: 'integer'),
                        new OA\Property(property: 'name', description: 'Customer name', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function update(Request $request, Response $response): Response
    {
        $dto = CustomerUpdateRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $this->customerService->rename($dto->id, $dto->name);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, [], 'Customer updated.');
    }

    #[OA\Delete(
        path: '/customer/delete',
        tags: [OpenApiTags::CUSTOMER],
        description: 'Delete a customer. Requires an admin token.',
        summary: 'Delete customer',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id'],
                    properties: [
                        new OA\Property(property: 'id', description: 'Customer id', type: 'integer'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Customer not found')]
    public function delete(Request $request, Response $response): Response
    {
        $dto = CustomerIdRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $this->customerService->delete($dto->id);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, [], 'Customer deleted.');
    }

    /**
     * @param array<string, string> $errors
     */
    private function validationError(Response $response, array $errors): Response
    {
        return JsonResponse::error($response, 'Validation failed.', $errors, [], HttpStatus::BAD_REQUEST);
    }
}
