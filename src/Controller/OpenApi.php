<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Slim 4 Starter Pack",
    version: "v1.0.0",
    description: "Backend API for the Slim 4 Starter Pack, please use responsibly.",
    contact: new OA\Contact(email: "admin@example.com")
)]
#[OA\SecurityScheme(
    securityScheme: "auth_token",
    type: "apiKey",
    in: "header",
    name: "Authorization",
    description: "JWT issued by the application; send the raw token or 'Bearer <token>'."
)]
final class OpenApi
{
}
