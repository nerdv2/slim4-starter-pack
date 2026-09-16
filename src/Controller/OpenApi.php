<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Customer DB API",
    version: "v1.0.0",
    description: "Customer database API: authentication with access and refresh tokens, customer management, avatars, CSV import/export and background jobs.",
    contact: new OA\Contact(email: "admin@example.com")
)]
#[OA\SecurityScheme(
    securityScheme: "auth_token",
    type: "apiKey",
    in: "header",
    name: "Authorization",
    description: "JWT issued by the application; send the raw token or 'Bearer <token>'."
)]
#[OA\SecurityScheme(
    securityScheme: "health_token",
    type: "apiKey",
    in: "header",
    name: "X-Health-Token",
    description: "Token configured through HEALTHCHECK_TOKEN for detailed health diagnostics."
)]
final class OpenApi
{
}
