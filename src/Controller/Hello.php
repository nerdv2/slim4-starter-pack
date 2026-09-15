<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\OpenApiTags;
use App\Helper\JsonResponse;
use App\Helper\TwigResponse;
use App\Model\HelloModel;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class Hello extends BaseController
{
    private HelloModel $helloModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->helloModel = new HelloModel();
    }

    #[OA\Get(
        path: '/',
        tags: [OpenApiTags::DEFAULT],
        description: 'Application name and active version.',
        summary: 'Application status'
    )]
    #[OA\Response(response: 200, description: 'Success')]
    public function getStatus(Request $request, Response $response): Response
    {
        return JsonResponse::success($response, [
            'name' => (string) ($_SERVER['APP_NAME'] ?? 'Slim 4 Starter Pack'),
            'version' => (string) ($_SERVER['APP_VERSION'] ?? 'dev'),
        ], 'Application is running');
    }

    #[OA\Get(
        path: '/status',
        tags: [OpenApiTags::DEFAULT],
        description: 'Retrieves application status and active version.',
        summary: 'Application status payload'
    )]
    #[OA\Response(response: 200, description: 'Success')]
    public function getStatusAPI(Request $request, Response $response): Response
    {
        return JsonResponse::success($response, [
            'message' => $this->helloModel->getHello(),
        ]);
    }

    #[OA\Get(
        path: '/swaggerui',
        tags: [OpenApiTags::DEFAULT],
        description: 'Bundled Swagger UI for the generated OpenAPI specification.',
        summary: 'API documentation UI'
    )]
    #[OA\Response(response: 200, description: 'HTML page')]
    public function openSwaggerUI(Request $request, Response $response): Response
    {
        return TwigResponse::render($request, $response, 'swagger/view.twig', []);
    }
}
