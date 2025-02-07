<?php

declare(strict_types=1);

namespace App\Helper;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

final class TwigResponse
{
    public static function render(Request $request, Response $response, string $template, array $data, int $status = 200)
    {
        $view = Twig::fromRequest($request);

        $http = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://';
        $newurl = str_replace("index.php", "", $_SERVER['SCRIPT_NAME']);
        $data['baseurl']    = "$http" . $_SERVER['SERVER_NAME'] . "" . $newurl;

        if(in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1', 'localhost'))){
            $data['baseurl']    = "$http" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . "" . $newurl;
        }

        $data['uri'] = $_SERVER['REQUEST_URI'];

        return $view->render($response, $template, $data);
    }
}
