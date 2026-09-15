<?php

declare(strict_types=1);

use App\Constants\HttpStatus;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/** @var App $app */
$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app): Response {
    $statusCode = HttpStatus::INTERNAL_SERVER_ERROR;
    if (is_int($exception->getCode()) &&
        $exception->getCode() >= 400 &&
        $exception->getCode() <= 599
    ) {
        $statusCode = $exception->getCode();
    }

    $className = (new ReflectionClass($exception))->getShortName();

    $data = [
        'message' => $displayErrorDetails
            ? $exception->getMessage()
            : 'An unexpected server error occurred.',
        'status' => 'error',
        'code' => $statusCode,
    ];
    if ($displayErrorDetails) {
        $data['class'] = $className;
        $data['file'] = $exception->getFile() . ':' . $exception->getLine();
    }

    $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write($body === false ? '{"status":"error","code":' . $statusCode . '}' : $body);

    if ($logErrors) {
        static $log = null;
        if ($log === null) {
            $logPath = __DIR__ . '/../../storage/log/';
            if (!is_dir($logPath)) {
                mkdir($logPath, 0775, true);
            }

            $log = new Logger('app');
            $log->pushHandler(new StreamHandler($logPath . 'error.log', Level::Error));
        }

        $context = [
            'status' => $statusCode,
            'class' => $className,
            'request_uri' => $request->getUri()->getPath(),
        ];
        if ($logErrorDetails) {
            $context['exception'] = $exception;
        }

        $log->error($exception->getMessage(), $context);
    }

    return $response
        ->withStatus($statusCode)
        ->withHeader('Content-type', 'application/problem+json');
};

return $customErrorHandler;
