<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JsonResponse;
use App\Helper\TwigResponse;
use Pimple\Psr11\Container;
use App\Model\HelloModel;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class Hello
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $data['message'] = "Hello world!";

        return TwigResponse::render($request, $response, "hello.twig", $data, 200);
    }

    public function getStatusAPI(Request $request, Response $response): Response
    {
        $this->hellomodel = new HelloModel();

        $result['status'] = true;
        $result['message'] = $this->hellomodel->getHello();

        return JsonResponse::withJson($response, $result, 200);
    }
}
