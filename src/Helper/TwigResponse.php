<?php

declare(strict_types=1);

namespace App\Helper;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;

final class TwigResponse
{
    public static function render(Request $request, Response $response, string $template, array $data, int $status = 200)
    {
        $view = Twig::fromRequest($request);


        $http = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://';
        $newurl = str_replace("index.php", "", $_SERVER['SCRIPT_NAME']);
        $data['baseurl']    = "$http" . $_SERVER['SERVER_NAME'] . "" . $newurl;

        return $view->render($response, $template, $data);
    }
}
