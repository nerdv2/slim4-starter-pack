# API Conventions

Shared rules for every HTTP endpoint.

## Base URL

| Environment | URL |
|-------------|-----|
| Local | `http://localhost:8080` (`composer run serve`) |

Production traffic should use HTTPS; TLS and CORS can be terminated at the webserver or reverse
proxy.

## Response Envelope

JSON endpoints answer with the shared envelope:

```json
{
    "status": true,
    "message": "Data ditemukan",
    "data": {}
}
```

Failure:

```json
{
    "status": false,
    "message": "Name is required.",
    "data": []
}
```

Build the envelope with the helpers instead of assembling arrays by hand:

```php
return JsonResponse::success($response, $data);                       // status true, default message
return JsonResponse::success($response, $data, 'Customer created.');  // custom message
return JsonResponse::success($response, $data, 'Data ditemukan', [    // extra top-level keys
    'total_page' => Pagination::totalPages($totalData, $limit),
    'total_data' => $totalData,
]);
return JsonResponse::error($response, 'Name is required.', [], [], HttpStatus::BAD_REQUEST);
return JsonResponse::notFound($response);                             // "Data tidak ditemukan"
```

| Helper | Signature |
|--------|-----------|
| `success()` | `success($response, $data = [], $message = 'Data ditemukan', $extra = [], $httpStatus = 200)` |
| `error()` | `error($response, $message, $data = [], $extra = [], $httpStatus = 200)` |
| `notFound()` | `notFound($response, $data = [], $extra = [], $httpStatus = 200)` |
| `withJson()` | Low-level writer for payloads that are not the standard envelope. |

### Validation and Not-Found Errors

Invalid payloads return `400` with the field messages in `data`:

```json
{
    "status": false,
    "message": "Validation failed.",
    "data": {
        "name": "Name is required."
    }
}
```

Resources that do not exist return `404`:

```json
{
    "status": false,
    "message": "Customer not found.",
    "data": []
}
```

Typed exceptions (`ValidationException`, `NotFoundException`) become these responses through
`BaseController::errorResponse()`; services throw them and controllers catch `AppException`.

Notes:

- `status` is a boolean on the envelope; the uncaught-exception payload (see
  [Errors](#errors-from-uncaught-exceptions)) uses `status: "error"` instead.
- Responses use `application/json;charset=utf-8`; encoding failures fall back to a 500 envelope.
- `error()` defaults to HTTP 200 for legacy compatibility; pass `$httpStatus` when a real 4xx/5xx
  code is required (the example module does).

## Pagination

List endpoints accept:

| Parameter | Default | Notes |
|-----------|---------|-------|
| `page` | `1` | 1-based; values below 1 are clamped to 1 |
| `limit` | `20` | Clamped to `1..100` (`Pagination::MAX_LIMIT`) |
| `keywords` | empty | Case-insensitive search where supported |

Rules live in `App\Helper\Pagination`:

```php
[$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null);
$totalData = $this->model->countList($keywords);
$data = $this->model->list($keywords, $page, $limit);

return JsonResponse::success($response, $data, JsonResponse::DEFAULT_SUCCESS_MESSAGE, [
    'total_page' => Pagination::totalPages($totalData, $limit),
    'total_data' => $totalData,
]);
```

- `Pagination::sanitize()` returns `[page, limit]`; `apply()` adds LIMIT/OFFSET to a builder and
  returns the effective limit; `totalPages()` never divides by zero; `clamp()` bounds a limit.
- Always pair a list method with a `count*` sibling that runs `COUNT(*)` instead of counting rows in
  PHP, and always provide a deterministic `ORDER BY` with a unique tie-breaker (`id`).

Example response:

```json
{
    "status": true,
    "message": "Data ditemukan",
    "data": [],
    "total_page": 7,
    "total_data": 65
}
```

## Authentication

Protected endpoints use a short-lived JWT in the `Authorization` header. Both forms are accepted:

```http
Authorization: <token>
Authorization: Bearer <token>
```

Access tokens are HMAC-SHA256 JWTs built by `App\Helper\JwtHelper`; user claims are mapped to
`id`, `email`, `name` and `type` (`admin`/`staff`). A token without `id` is rejected.

| Setting | Environment variable | Default |
|---------|----------------------|---------|
| Signing key | `JWT_SECRET` | none (required, minimum 32 bytes) |
| Issuer / audience | `APP_BASE_URL` | none (required) |
| JWT ID (`jti`) | `JWT_IDENTIFIER` | `4f1g23a12aa` |
| Access token lifetime | `JWT_ACCESS_TTL` | `+15 minute` |

Sessions are restored through `POST /auth/refresh`, which reads the opaque HttpOnly refresh cookie
and rotates it on every use. See [Authentication](authentication.md) for the full flow, cookie
attributes and reuse detection.

Protect a route by adding middleware (the last added runs first):

```php
$app->post('/customer', 'App\Controller\Customer:create')
    ->setName('api.customer.create')
    ->add(new AuthorizationMiddleware([UserType::ADMIN]))
    ->add(new AuthenticationMiddleware());
```

Failures use the standard envelope with a real HTTP status:

| Case | Status | Message |
|------|--------|---------|
| Missing/invalid/expired token | `401` | `Authorization token is missing or invalid.` |
| Valid token, disallowed type | `403` | `Access denied.` |

Generate a development token without a user table:

```bash
composer run token -- id=1 type=admin
```

A missing or too-short `JWT_SECRET` raises a configuration error (HTTP 500, and the message is
visible when `DISPLAY_ERROR_DETAILS=true`); it never silently allows the request.

## Request ID

Every response carries `X-Request-ID`. Send your own to correlate logs across systems; otherwise
the application generates `YYYYMMDD-{8 hex}-{4 hex}`. Log entries for uncaught exceptions include
the same id.

## Health Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/health` | none | Liveness probe: `{status: "ok", timestamp}`. |
| `GET` | `/health/ready` | none | Readiness probe: database check, `503` when degraded. |
| `GET` | `/health/detailed` | `X-Health-Token` | Database, storage and memory details; `401` without a valid token and `503` when no token is configured outside development/testing. |

Health responses are plain JSON rather than the standard envelope so probes can consume them
directly. Configure `HEALTHCHECK_TOKEN` in every deployed environment.

## Errors from Uncaught Exceptions

`src/App/ErrorHandler.php` answers with `application/problem+json`:

```json
{
    "message": "An unexpected server error occurred.",
    "status": "error",
    "code": 500
}
```

- The HTTP status is the exception code when it is `400..599`, otherwise `500`.
- `class` and `file` are added only when `DISPLAY_ERROR_DETAILS=true` (development).
- 404s thrown by the catch-all route have the same shape with `code: 404` and
  `class: HttpNotFoundException` in development.

## CORS

CORS is a development aid; in production it is normally handled by the webserver or reverse proxy.

- `CORS_ENABLED` — when unset, CORS is enabled for `APP_ENVIRONMENT=development/testing` or when
  `SERVER_NAME=localhost`; set `false` to disable it everywhere.
- `CORS_ALLOWED_ORIGINS` — comma-separated exact origins. When empty, `Access-Control-Allow-Origin:
  *` is returned. When set, only listed origins are reflected (with `Vary: Origin`) and other
  origins receive no CORS headers.
- `CORS_ALLOW_CREDENTIALS` — `true` adds `Access-Control-Allow-Credentials` for allowed origins.
- `CORS_ALLOWED_HEADERS`, `CORS_ALLOWED_METHODS`, `CORS_MAX_AGE` — response values (defaults:
  `X-Requested-With, X-Client-Type, Content-Type, Accept, Origin, Authorization`;
  `GET, POST, PUT, DELETE, PATCH, OPTIONS`; `86400`).

`OPTIONS /{routes:.+}` (preflight) is answered with the configured headers; the catch-all route
handles the request body-less preflight before routing.

## File Uploads

Uploads are handled by `App\Helper\UploadHelper` (local filesystem or S3-compatible storage via
`DEFAULT_UPLOAD_TARGET`, `S3_*`). The customer module wires it to two endpoints:

| Endpoint | Field | Rules |
|----------|-------|-------|
| `POST /customer/{id}/avatar` | `avatar` | JPEG/PNG/WebP up to `UPLOAD_AVATAR_MAX_BYTES` (default 2 MB). Replacing or deleting a customer removes the old file. |
| `POST /customer/import` | `file` | `.csv` up to `UPLOAD_IMPORT_MAX_BYTES` (default 5 MB). Stored under `storage/imports/`, processed by the queue and deleted afterwards. |

Both validate extension, MIME type and size before trusting client filenames. Avatar URLs are
absolute (`APP_BASE_URL` + `uploads/avatars/...`); export and import files stay under `storage/`
and are only reachable through authenticated endpoints.

## Related Docs

- [Architecture](architecture.md)
- [Database](database.md)
- [Development](development.md)
