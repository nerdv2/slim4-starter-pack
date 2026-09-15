<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\Helper\JsonResponse;
use App\Helper\Pagination;
use App\Model\CustomerModel;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class Customer extends BaseController
{
    private CustomerModel $customerModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->customerModel = new CustomerModel($this->db());
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

        $totalData = $this->customerModel->countGet($keywords);
        $data = $this->customerModel->get($keywords, $page, $limit);

        return JsonResponse::success($response, $data, JsonResponse::DEFAULT_SUCCESS_MESSAGE, [
            'total_page' => Pagination::totalPages($totalData, $limit),
            'total_data' => $totalData,
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
        $name = trim((string) (($request->getParsedBody() ?? [])['name'] ?? ''));
        if ($name === '') {
            return JsonResponse::error($response, 'Name is required.', [], [], HttpStatus::BAD_REQUEST);
        }

        if (!$this->customerModel->add($name)) {
            return JsonResponse::error(
                $response,
                'Customer name already exists.',
                [],
                [],
                HttpStatus::BAD_REQUEST
            );
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
    public function update(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody() ?? [];
        $id = filter_var($post['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($post['name'] ?? ''));

        if ($id === false || $id <= 0 || $name === '') {
            return JsonResponse::error(
                $response,
                'A positive id and a name are required.',
                [],
                [],
                HttpStatus::BAD_REQUEST
            );
        }

        $this->customerModel->update($id, $name);

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
    public function delete(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody() ?? [];
        $id = filter_var($post['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            return JsonResponse::error(
                $response,
                'A positive id is required.',
                [],
                [],
                HttpStatus::BAD_REQUEST
            );
        }

        $this->customerModel->delete($id);

        return JsonResponse::success($response, [], 'Customer deleted.');
    }
}
