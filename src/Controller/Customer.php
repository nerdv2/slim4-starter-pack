<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JsonResponse;
use Pimple\Psr11\Container;

use App\Model\CustomerModel;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

final class Customer
{
    private $container;
    private $customerModel;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->customerModel            = new CustomerModel($this->container->get('db'));
    }

    #[OA\Get(
        path: '/customer', 
        tags: ["Customer"], 
        description: 'Retrieve data for customer.', 
        summary: "Retrieve data for customer",
        parameters: [
            new OA\Parameter(
                name: "filter",
                in: "query",
                schema: new OA\Schema(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "keywords",
                            description: "[OPTIONAL] Search keywords",
                            type: "string",
                            default: ""
                        )
                    ]
                )
            ),
        ]
    )]
    #[OA\Response(response: '200', description: "Success")]
    public function get(Request $request, Response $response): Response
    {
        $get                = $request->getQueryParams();
        $keywords           = !empty($get['keywords']) ? $get['keywords'] : "";
        $result['data']     = $this->customerModel->get($keywords);
        

        return JsonResponse::withJson($response, $result, 200);
    }

    #[OA\Post(
        path: '/customer/add', 
        tags: ["Customer"], 
        description: 'Add new customer data.', 
        summary: "Add new customer data",
        requestBody: new OA\RequestBody(
            required: true, 
            description: "Data", 
            content: new OA\MediaType(
                mediaType: "application/x-www-form-urlencoded",
                schema: new OA\Schema(
                    type: "object",
                    required: ["name"],
                    properties: [
                        new OA\Property(
                            property: "name",
                            description: "Name",
                            type: "string"
                        )
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: '200', description: "Success")]
    public function add(Request $request, Response $response): Response
    {
        $post           = $request->getParsedBody();
        $name           = isset($post["name"]) ? $post["name"] : '';

        $result['status']   = $this->customerModel->add($name);

        return JsonResponse::withJson($response, $result, 200);
    }

    #[OA\Post(
        path: '/customer/update', 
        tags: ["Customer"], 
        description: 'Update customer data.', 
        summary: "Update customer data",
        requestBody: new OA\RequestBody(
            required: true, 
            description: "Data", 
            content: new OA\MediaType(
                mediaType: "application/x-www-form-urlencoded",
                schema: new OA\Schema(
                    type: "object",
                    required: ["id", "name"],
                    properties: [
                        new OA\Property(
                            property: "id",
                            description: "ID",
                            type: "string"
                        ),
                        new OA\Property(
                            property: "name",
                            description: "Name",
                            type: "string"
                        )
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: '200', description: "Success")]
    public function update(Request $request, Response $response): Response
    {
        $post           = $request->getParsedBody();
        $id             = isset($post["id"]) ? $post["id"] : '';
        $name           = isset($post["name"]) ? $post["name"] : '';

        $result['status']   = $this->customerModel->update($id, $name);

        return JsonResponse::withJson($response, $result, 200);
    }

    #[OA\Delete(
        path: '/customer/delete', 
        tags: ["Customer"], 
        description: 'Delete customer data.', 
        summary: "Delete customer data",
        requestBody: new OA\RequestBody(
            required: true, 
            description: "Data", 
            content: new OA\MediaType(
                mediaType: "application/x-www-form-urlencoded",
                schema: new OA\Schema(
                    type: "object",
                    required: ["id"],
                    properties: [
                        new OA\Property(
                            property: "id",
                            description: "ID",
                            type: "int"
                        )
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: '200', description: "Success")]
    public function delete(Request $request, Response $response): Response
    {
        $post           = $request->getParsedBody();
        $id             = isset($post["id"]) ? $post["id"] : '';

        $result['status']   = $this->customerModel->delete($id);

        return JsonResponse::withJson($response, $result, 200);
    }
}
