<?php

declare(strict_types=1);

use Sentry\Event;
use Sentry\EventHint;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;

// Error monitoring is optional: everything is skipped when SENTRY_DSN is empty.
// Request bodies are never attached (they can contain passwords or tokens) and
// query strings are stripped because they may carry tokens or coordinates.
$sentryDsn = $_SERVER['SENTRY_DSN'] ?? $_ENV['SENTRY_DSN'] ?? getenv('SENTRY_DSN');

if (is_string($sentryDsn) && $sentryDsn !== '') {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => (string) ($_SERVER['APP_ENVIRONMENT'] ?? $_ENV['APP_ENVIRONMENT'] ?? 'production'),
        'release' => (string) ($_SERVER['APP_VERSION'] ?? $_ENV['APP_VERSION'] ?? 'dev'),
        'send_default_pii' => false,
        'max_request_body_size' => 'none',
        'before_send' => static function (Event $event, ?EventHint $hint = null): Event {
            $request = $event->getRequest();
            unset($request['query_string']);

            if (isset($request['url']) && is_string($request['url'])) {
                $url = strtok($request['url'], '?');
                if ($url !== false) {
                    $request['url'] = $url;
                }
            }

            $event->setRequest($request);

            return $event;
        },
        'integrations' => static function (array $integrations): array {
            return array_map(
                static function (IntegrationInterface $integration): IntegrationInterface {
                    if (!$integration instanceof RequestIntegration) {
                        return $integration;
                    }

                    return new RequestIntegration(null, [
                        'pii_sanitize_headers' => [
                            'authorization',
                            'proxy-authorization',
                            'cookie',
                            'set-cookie',
                            'x-health-token',
                        ],
                    ]);
                },
                $integrations
            );
        },
    ]);
}
