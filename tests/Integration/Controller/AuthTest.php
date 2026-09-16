<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function testRegisterCreatesAStaffAccountAndSession(): void
    {
        $response = $this->handle($this->createRequest('POST', '/auth/register', [], [
            'name' => 'Jane Doe',
            'email' => 'Jane@Example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]));
        $payload = $this->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertTrue($payload['status']);
        self::assertSame('staff', $payload['data']['user']['type']);
        self::assertSame('jane@example.com', $payload['data']['user']['email']);
        self::assertArrayHasKey('access_token', $payload['data']);
        self::assertArrayNotHasKey('refresh_token', $payload['data'], 'The refresh token must stay in the cookie.');
        self::assertNotNull($this->responseCookie($response));

        $me = $this->handle($this->createRequest('GET', '/auth/me', [
            'Authorization' => 'Bearer ' . $payload['data']['access_token'],
        ]));
        self::assertSame(200, $me->getStatusCode());
        self::assertSame('jane@example.com', $this->json($me)['data']['email']);
    }

    public function testRegisterRejectsDuplicateAndInvalidPayloads(): void
    {
        $this->createUser('staff', 'Password123!', ['email' => 'taken@example.com']);

        $duplicate = $this->handle($this->createRequest('POST', '/auth/register', [], [
            'name' => 'Jane',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]));
        self::assertSame(400, $duplicate->getStatusCode());

        $invalid = $this->handle($this->createRequest('POST', '/auth/register', [], [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]));
        $errors = $this->json($invalid)['data'];

        self::assertSame(400, $invalid->getStatusCode());
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('password', $errors);
        self::assertArrayHasKey('password_confirmation', $errors);
    }

    public function testLoginRejectsInvalidCredentialsWithoutLeakingAccountExistence(): void
    {
        $user = $this->createUser('admin', 'Password123!');

        $wrongPassword = $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => $user['email'],
            'password' => 'WrongPassword!',
        ]));
        $unknownEmail = $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => 'nobody@example.com',
            'password' => 'Password123!',
        ]));

        self::assertSame(401, $wrongPassword->getStatusCode());
        self::assertSame(401, $unknownEmail->getStatusCode());
        self::assertSame(
            $this->json($wrongPassword)['message'],
            $this->json($unknownEmail)['message']
        );
    }

    public function testLoginStartsASessionAndTracksLastLogin(): void
    {
        $user = $this->createUser('admin', 'Password123!');

        $response = $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => strtoupper($user['email']),
            'password' => 'Password123!',
        ]));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('admin', $payload['data']['user']['type']);
        self::assertNotNull($this->responseCookie($response));

        $stored = $this->connection()->table('user')->where('user.id', '=', $user['id'])->first();
        self::assertNotNull($stored);
        self::assertNotNull($stored->last_login_at);
    }

    public function testRefreshRotatesTheTokenAndRevokesTheFamilyOnReuse(): void
    {
        $user = $this->createUser('staff', 'Password123!');
        $login = $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => $user['email'],
            'password' => 'Password123!',
        ]));
        $original = $this->responseCookie($login);
        self::assertNotNull($original);

        $refreshed = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $original
        ));
        $rotated = $this->responseCookie($refreshed);

        self::assertSame(200, $refreshed->getStatusCode());
        self::assertNotNull($rotated);
        self::assertNotSame($original, $rotated);
        self::assertArrayHasKey('access_token', $this->json($refreshed)['data']);

        // Replaying the rotated token kills the whole family.
        $reuse = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $original
        ));
        self::assertSame(401, $reuse->getStatusCode());

        $afterReuse = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $rotated
        ));
        self::assertSame(401, $afterReuse->getStatusCode());
    }

    public function testRefreshWithoutCookieIsUnauthorized(): void
    {
        $response = $this->handle($this->createRequest('POST', '/auth/refresh'));

        self::assertSame(401, $response->getStatusCode());
        self::assertNotNull($this->responseCookie($response), 'The stale cookie should be cleared.');
    }

    public function testLogoutRevokesTheSessionFamily(): void
    {
        $user = $this->createUser('staff', 'Password123!');
        $login = $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => $user['email'],
            'password' => 'Password123!',
        ]));
        $token = $this->responseCookie($login);
        self::assertNotNull($token);

        $logout = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/logout'),
            $token
        ));
        self::assertSame(200, $logout->getStatusCode());
        self::assertSame('', $this->responseCookie($logout));

        $refresh = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $token
        ));
        self::assertSame(401, $refresh->getStatusCode());
    }

    public function testMeRequiresAuthentication(): void
    {
        self::assertSame(401, $this->handle($this->createRequest('GET', '/auth/me'))->getStatusCode());
    }

    public function testProfileUpdateAndPasswordChange(): void
    {
        $user = $this->createUser('admin', 'Password123!');
        $headers = ['Authorization' => $this->bearerFor($user)];

        $profile = $this->handle($this->createRequest('PUT', '/auth/profile', $headers, [
            'name' => 'Renamed Admin',
        ]));
        self::assertSame(200, $profile->getStatusCode());
        self::assertSame('Renamed Admin', $this->json($profile)['data']['name']);

        $firstToken = $this->responseCookie($this->loginResponse($user['email'], 'Password123!'));
        $secondToken = $this->responseCookie($this->loginResponse($user['email'], 'Password123!'));
        self::assertNotNull($firstToken);
        self::assertNotNull($secondToken);
        self::assertNotSame($firstToken, $secondToken);

        $change = $this->handle($this->withRefreshCookie(
            $this->createRequest(
                'POST',
                '/auth/change-password',
                $headers,
                [
                    'current_password' => 'Password123!',
                    'new_password' => 'NewPassword456!',
                    'new_password_confirmation' => 'NewPassword456!',
                ]
            ),
            $firstToken
        ));
        self::assertSame(200, $change->getStatusCode());

        // The old password no longer works; the new one does.
        self::assertSame(401, $this->loginResponse($user['email'], 'Password123!')->getStatusCode());
        self::assertSame(200, $this->loginResponse($user['email'], 'NewPassword456!')->getStatusCode());

        // The session behind the change request stays valid, other sessions die.
        $kept = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $firstToken
        ));
        self::assertSame(200, $kept->getStatusCode());

        $revoked = $this->handle($this->withRefreshCookie(
            $this->createRequest('POST', '/auth/refresh'),
            $secondToken
        ));
        self::assertSame(401, $revoked->getStatusCode());
    }

    public function testChangePasswordRejectsAWrongCurrentPassword(): void
    {
        $user = $this->createUser('admin', 'Password123!');

        $response = $this->handle($this->createRequest(
            'POST',
            '/auth/change-password',
            ['Authorization' => $this->bearerFor($user)],
            [
                'current_password' => 'NotMyPassword!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ]
        ));

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('current_password', $this->json($response)['data']);
    }

    private function loginResponse(string $email, string $password): ResponseInterface
    {
        return $this->handle($this->createRequest('POST', '/auth/login', [], [
            'email' => $email,
            'password' => $password,
        ]));
    }
}
