# Authentication

Access + refresh token authentication with rotating, hashed refresh tokens.

## Token Model

| Token | Format | Lifetime | Transport |
|-------|--------|----------|-----------|
| Access token | HS256 JWT (`JwtHelper`) | `JWT_ACCESS_TTL` (default `+15 minute`) | `Authorization: Bearer <token>` header; kept in client memory. |
| Refresh token | 64-char opaque random string | `REFRESH_TOKEN_TTL_DAYS` (default 30 days) | HttpOnly cookie (`refresh_token`); only the SHA-256 hash is stored in `refresh_token`. |

Access tokens carry the standard claims (`iss`, `aud`, `jti`, `iat`, `nbf`, `exp`) plus
`id`, `email`, `name` and `type`. `type` drives `AuthorizationMiddleware` (`admin`, `staff`).
Because access tokens are stateless, a logout leaves the current access token valid until it
expires (15 minutes by default); nothing else can be done without a denylist.

## Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/auth/register` | — | Create a `staff` account and start a session. |
| `POST` | `/auth/login` | — | Email + password → access token + refresh cookie. |
| `POST` | `/auth/refresh` | refresh cookie | Rotate the cookie and issue a new access token. |
| `POST` | `/auth/logout` | refresh cookie | Revoke the session family and clear the cookie. |
| `GET` | `/auth/me` | access token | Current account. |
| `PUT` | `/auth/profile` | access token | Update the display name. |
| `POST` | `/auth/change-password` | access token | Change the password; other sessions are revoked. |

The refresh token is never returned in a JSON body: it only travels in the `Set-Cookie` header.

## Rotation and Reuse Detection

Every refresh rotates the token inside its **session family**:

1. `POST /auth/refresh` looks up the SHA-256 hash of the presented token.
2. A missing, expired or revoked token answers `401`.
3. A valid token is revoked and replaced by a new token in the same family.
4. If a **rotated** token is presented again, the whole family is revoked — the assumption is that
   the token leaked. All devices in that session must sign in again.

Changing the password revokes every session except the family that made the request. Logout revokes
the family of the presented refresh token.

## Cookie Attributes

| Attribute | Value |
|-----------|-------|
| `HttpOnly` | Always. JavaScript cannot read the token. |
| `SameSite` | `AUTH_COOKIE_SAMESITE` (default `Lax`); blocks cross-site POSTs. |
| `Secure` | `AUTH_COOKIE_SECURE`, or derived from `APP_ENVIRONMENT` (on outside development/testing/local). |
| `Path` | `/`. |
| `Max-Age` | `REFRESH_TOKEN_TTL_DAYS` × 86400. |

The SPA must send credentialed requests (`fetch(..., { credentials: 'include' })`). With CORS that
requires an exact origin allowlist (`CORS_ALLOWED_ORIGINS`) and `CORS_ALLOW_CREDENTIALS=true`;
browsers reject credentialed requests against a wildcard origin.

## Roles

| Role | Read customers | Write customers / import | Export |
|------|----------------|--------------------------|--------|
| `staff` | ✅ | ❌ (403) | ✅ |
| `admin` | ✅ | ✅ | ✅ |

`POST /auth/register` always creates `staff`. The bootstrap `admin` account comes from the seeder
(`composer run seed`), see the README for the default credentials. Replace them before deploying.

## Configuration

```dotenv
JWT_SECRET=...                 # openssl rand -hex 32
JWT_ACCESS_TTL="+15 minute"
REFRESH_TOKEN_TTL_DAYS=30
AUTH_COOKIE_SAMESITE=Lax
AUTH_COOKIE_SECURE=            # empty derives from APP_ENVIRONMENT
CORS_ALLOWED_ORIGINS="http://localhost:5173"
CORS_ALLOW_CREDENTIALS=true
```

## Development Tokens

`composer run token -- id=1 type=admin` still signs a long-lived JWT for local API testing
(`JWT_TTL`). It skips the user table: the token only needs `id` and `type` for endpoints that do not
read the account.

## Security Notes

- Passwords use `password_hash()` (bcrypt) with a minimum length of 8 and a 72-byte cap.
- Login answers the same message and spends the same time (dummy hash verification) for unknown
  emails and wrong passwords.
- Tokens are stored hashed; database reads cannot reveal a usable refresh token.
- Login rate limiting is intentionally left to the webserver/reverse proxy in this codebase.
