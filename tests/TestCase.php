<?php

declare(strict_types=1);

namespace Tests;

use App\Helper\JwtHelper;
use App\Helper\RefreshCookie;
use Oeltima\SimpleQuery\Connection;
use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class TestCase extends PHPUnitTestCase
{
    protected App $app;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = require dirname(__DIR__) . '/src/App/App.php';
        self::migrate();
        $this->cleanDatabase();
    }

    /**
     * Apply the Phinx migrations to the test database once per process.
     */
    private static function migrate(): void
    {
        if (self::$migrated) {
            return;
        }

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__) . '/phinx-testing.php';
        $manager = new Manager(new Config($config), new ArrayInput([]), new NullOutput());
        $manager->migrate('testing');

        self::$migrated = true;
    }

    /**
     * Remove every row from the application tables so tests start clean.
     */
    protected function cleanDatabase(): void
    {
        $pdo = $this->connection()->pdo();

        /** @var list<string> $tables */
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'phinxlog'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $pdo->exec('DELETE FROM "' . $table . '"');
        }
    }

    protected function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->app->getContainer()->get('db');

        return $connection;
    }

    protected function readConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->app->getContainer()->get('db_read');

        return $connection;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $body
     * @param array<string, mixed>  $query
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        array $body = [],
        array $query = []
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        if ($query !== []) {
            $request = $request->withQueryParams($query);
        }

        return $request;
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Authorization header for a development token.
     */
    protected function authHeader(string $type = 'admin', int $id = 1): string
    {
        return 'Bearer ' . JwtHelper::buildToken(['id' => $id, 'type' => $type]);
    }

    /**
     * Insert an account and return its id and credentials.
     *
     * @param array<string, mixed> $overrides
     * @return array{id: int, email: string, password: string}
     */
    protected function createUser(
        string $type = 'admin',
        string $password = 'Password123!',
        array $overrides = []
    ): array {
        $row = TestFactory::user(array_merge([
            'type' => $type,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ], $overrides));

        $id = (int) $this->connection()->table('user')->insertGetId($row);

        return ['id' => $id, 'email' => (string) $row['email'], 'password' => $password];
    }

    /**
     * Read a cookie value from the Set-Cookie headers of a response.
     */
    protected function responseCookie(
        ResponseInterface $response,
        string $name = RefreshCookie::NAME
    ): ?string {
        foreach ($response->getHeaders()['Set-Cookie'] ?? [] as $header) {
            if (str_starts_with($header, $name . '=')) {
                $value = explode(';', substr($header, strlen($name) + 1), 2)[0];

                return rawurldecode($value);
            }
        }

        return null;
    }

    /**
     * Bearer header for a real account, so endpoints that read the database
     * resolve an existing user.
     *
     * @param array{id: int, email: string, password: string} $user
     */
    protected function bearerFor(array $user, string $type = 'admin'): string
    {
        return 'Bearer ' . JwtHelper::buildToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'type' => $type,
        ]);
    }

    /**
     * Attach the refresh cookie the way the web server does ($_COOKIE), so
     * RefreshCookie::fromRequest() sees it.
     */
    protected function withRefreshCookie(ServerRequestInterface $request, string $token): ServerRequestInterface
    {
        return $request->withCookieParams([RefreshCookie::NAME => $token]);
    }
}
