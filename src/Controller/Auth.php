<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\HttpStatus;
use App\Constants\OpenApiTags;
use App\DTO\Request\ChangePasswordRequest;
use App\DTO\Request\LoginRequest;
use App\DTO\Request\RegisterRequest;
use App\DTO\Request\UpdateProfileRequest;
use App\Exceptions\AppException;
use App\Helper\JsonResponse;
use App\Helper\RefreshCookie;
use App\Service\AuthService;
use OpenApi\Attributes as OA;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class Auth extends BaseController
{
    private AuthService $authService;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->authService = $container->get('authService');
    }

    #[OA\Post(
        path: '/auth/register',
        tags: [OpenApiTags::AUTH],
        description: 'Create a staff account and start a session. The first administrator is created by the seeder.',
        summary: 'Register',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name', 'email', 'password', 'password_confirmation'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'password', type: 'string', format: 'password'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Account created')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    public function register(Request $request, Response $response): Response
    {
        $dto = RegisterRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $session = $this->authService->register($dto, $this->userAgent($request), $this->ipAddress($request));
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return $this->sessionResponse($response, $session, 'Account created.', HttpStatus::CREATED);
    }

    #[OA\Post(
        path: '/auth/login',
        tags: [OpenApiTags::AUTH],
        description: 'Exchange email and password for an access token and an HttpOnly refresh cookie.',
        summary: 'Login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['email', 'password'],
                    properties: [
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'password', type: 'string', format: 'password'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Signed in')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    public function login(Request $request, Response $response): Response
    {
        $dto = LoginRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $session = $this->authService->login($dto, $this->userAgent($request), $this->ipAddress($request));
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return $this->sessionResponse($response, $session, 'Signed in.');
    }

    #[OA\Post(
        path: '/auth/refresh',
        tags: [OpenApiTags::AUTH],
        description: 'Rotate the refresh cookie and issue a new access token. Reusing a rotated token revokes the session family.',
        summary: 'Refresh session'
    )]
    #[OA\Response(response: 200, description: 'Session refreshed')]
    #[OA\Response(response: 401, description: 'Missing, expired or reused refresh token')]
    public function refresh(Request $request, Response $response): Response
    {
        $token = RefreshCookie::fromRequest($request);
        if ($token === null) {
            return $this->clearCookie(JsonResponse::error(
                $response,
                'No active session.',
                [],
                [],
                HttpStatus::UNAUTHORIZED
            ));
        }

        try {
            $session = $this->authService->refresh($token, $this->userAgent($request), $this->ipAddress($request));
        } catch (AppException $exception) {
            return $this->clearCookie($this->errorResponse($response, $exception));
        }

        return $this->sessionResponse($response, $session, 'Session refreshed.');
    }

    #[OA\Post(
        path: '/auth/logout',
        tags: [OpenApiTags::AUTH],
        description: 'Revoke the current refresh-token family and clear the cookie.',
        summary: 'Logout'
    )]
    #[OA\Response(response: 200, description: 'Signed out')]
    public function logout(Request $request, Response $response): Response
    {
        $token = RefreshCookie::fromRequest($request);
        if ($token !== null) {
            $this->authService->logout($token);
        }

        return $this->clearCookie(JsonResponse::success($response, [], 'Signed out.'));
    }

    #[OA\Get(
        path: '/auth/me',
        tags: [OpenApiTags::AUTH],
        description: 'Current account profile.',
        summary: 'Current user',
        security: [['auth_token' => []]]
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function me(Request $request, Response $response): Response
    {
        $authUser = $this->user($request);
        if ($authUser === null) {
            return JsonResponse::error($response, 'Authentication required.', [], [], HttpStatus::UNAUTHORIZED);
        }

        try {
            $user = $this->authService->user((int) $authUser->id);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $user);
    }

    #[OA\Put(
        path: '/auth/profile',
        tags: [OpenApiTags::AUTH],
        description: 'Update the display name of the current account.',
        summary: 'Update profile',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Profile updated')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function updateProfile(Request $request, Response $response): Response
    {
        $authUser = $this->user($request);
        if ($authUser === null) {
            return JsonResponse::error($response, 'Authentication required.', [], [], HttpStatus::UNAUTHORIZED);
        }

        $dto = UpdateProfileRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $user = $this->authService->updateProfile((int) $authUser->id, $dto->name);
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, $user, 'Profile updated.');
    }

    #[OA\Post(
        path: '/auth/change-password',
        tags: [OpenApiTags::AUTH],
        description: 'Change the password and revoke every other active session.',
        summary: 'Change password',
        security: [['auth_token' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['current_password', 'new_password', 'new_password_confirmation'],
                    properties: [
                        new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                        new OA\Property(property: 'new_password', type: 'string', format: 'password'),
                        new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password'),
                    ]
                )
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Password changed')]
    #[OA\Response(response: 400, description: 'Validation failed')]
    #[OA\Response(response: 401, description: 'Missing or invalid token')]
    public function changePassword(Request $request, Response $response): Response
    {
        $authUser = $this->user($request);
        if ($authUser === null) {
            return JsonResponse::error($response, 'Authentication required.', [], [], HttpStatus::UNAUTHORIZED);
        }

        $dto = ChangePasswordRequest::fromRequest($request);
        if (!$dto->isValid()) {
            return $this->validationError($response, $dto->validate());
        }

        try {
            $this->authService->changePassword(
                (int) $authUser->id,
                $dto,
                RefreshCookie::fromRequest($request)
            );
        } catch (AppException $exception) {
            return $this->errorResponse($response, $exception);
        }

        return JsonResponse::success($response, [], 'Password changed.');
    }

    /**
     * @param array<string, mixed> $session
     */
    private function sessionResponse(Response $response, array $session, string $message, int $status = HttpStatus::OK): Response
    {
        $refreshToken = (string) ($session['refresh_token'] ?? '');
        unset($session['refresh_token']);

        $response = JsonResponse::success($response, $session, $message, [], $status);
        if ($refreshToken === '') {
            return $response;
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            RefreshCookie::toHeader(RefreshCookie::create($refreshToken))
        );
    }

    private function clearCookie(Response $response): Response
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            RefreshCookie::toHeader(RefreshCookie::clear())
        );
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = trim($request->getHeaderLine('User-Agent'));

        return $userAgent === '' ? null : $userAgent;
    }

    private function ipAddress(Request $request): ?string
    {
        $params = $request->getServerParams();
        $ipAddress = $params['REMOTE_ADDR'] ?? null;
        if (!is_string($ipAddress) || trim($ipAddress) === '') {
            return null;
        }

        return $ipAddress;
    }
}
