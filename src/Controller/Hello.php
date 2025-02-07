<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JsonResponse;
use App\Helper\TwigResponse;
use Pimple\Psr11\Container;
use App\Model\HelloModel;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Slim4 Starter Pack", 
    version: "v1.0.0", 
    description: "This is backend API for Slim 4 Starter Pack, please use responsibly.",
    contact: new OA\Contact(email: "admin@example.com")
)]
final class Hello
{
    private Container $container;
    private $hellomodel;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $data['message'] = "Hello world!";

        return TwigResponse::render($request, $response, "hello.twig", $data, 200);
    }

    #[OA\Get(path: '/status', tags: ["Default"], description: 'Retrieves application status and active version.', summary: "Default route")]
    #[OA\Response(response: '200', description: "Success")]
    public function getStatusAPI(Request $request, Response $response): Response
    {
        $this->hellomodel = new HelloModel();

        $result['status'] = true;
        $result['message'] = $this->hellomodel->getHello();

        return JsonResponse::withJson($response, $result, 200);
    }
}
