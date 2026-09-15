<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TwigResponse
{
    /**
     * Render a Twig template as a PSR-7 response.
     *
     * `baseurl` comes from APP_BASE_URL (scheme + host + optional base path)
     * and falls back to the current request authority; `uri` is the request
     * target (path and query string).
     */
    public static function render(
        Request $request,
        Response $response,
        string $template,
        array $data,
        int $status = 200
    ): Response {
        $view = Twig::fromRequest($request);

        $baseUrl = rtrim((string) ($_SERVER['APP_BASE_URL'] ?? ''), '/');
        if ($baseUrl === '') {
            $uri = $request->getUri();
            $baseUrl = $uri->getScheme() . '://' . $uri->getAuthority();
        }

        $data['baseurl'] = $baseUrl . '/';
        $data['uri'] = $request->getRequestTarget();

        return $view
            ->render($response, $template, $data)
            ->withStatus($status);
    }
}
