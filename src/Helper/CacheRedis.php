<?php

declare(strict_types=1);

namespace App\Helper;

use InvalidArgumentException;
use Predis\ClientInterface;
use Throwable;

/**
 * Optional Redis cache.
 *
 * - Fail-open: when Redis is unconfigured or unreachable, every read returns
 *   null and every write is skipped so requests keep serving from the database.
 *   The instance disables itself for the rest of the request only.
 * - Namespaced keys: `bump()` increments a per-namespace generation counter so
 *   invalidation is a single INCR instead of a SCAN.
 * - Bounded payloads: values larger than MAX_PAYLOAD_BYTES are not cached.
 * - Bounded lifetime: every write requires a positive TTL, jittered by +/-10%.
 */
final class CacheRedis
{
    public const int DEFAULT_TTL = 300;

    /**
     * Largest JSON payload (bytes) accepted by setJson(). Oversized values are
     * skipped so one hot key can never dominate the Redis memory budget.
     */
    public const int MAX_PAYLOAD_BYTES = 262144;

    private const string DEFAULT_PREFIX = 'v1:';
    private const float CONNECT_TIMEOUT = 1.0;
    private const float READ_WRITE_TIMEOUT = 1.0;
    private const int JITTER_PERCENT = 10;
    private const int VERSION_FALLBACK = 0;

    private ?ClientInterface $client;

    private bool $disabled = false;

    private ?string $prefix = null;

    /** @var array<string, int> */
    private array $versions = [];

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
    }

    /**
     * Whether a Redis connection is available in this request.
     */
    public function isEnabled(): bool
    {
        return $this->client() !== null;
    }

    /**
     * Expose the lazily built client (used by infrastructure that can reuse
     * the connection). Null when Redis is unconfigured or unreachable.
     */
    public function client(): ?ClientInterface
    {
        if ($this->disabled) {
            return null;
        }
        if ($this->client !== null) {
            return $this->client;
        }

        $host = trim((string) ($_SERVER['REDIS_SERVER_HOST'] ?? $_ENV['REDIS_SERVER_HOST'] ?? ''));
        $port = trim((string) ($_SERVER['REDIS_SERVER_PORT'] ?? $_ENV['REDIS_SERVER_PORT'] ?? ''));
        if ($host === '' || $port === '') {
            $this->disabled = true;

            return null;
        }

        $password = (string) ($_SERVER['REDIS_SERVER_PASSWORD'] ?? $_ENV['REDIS_SERVER_PASSWORD'] ?? '');
        $database = (int) trim((string) (
            $_SERVER['REDIS_SERVER_DATABASE'] ?? $_ENV['REDIS_SERVER_DATABASE'] ?? '0'
        ));
        if ($database < 0 || $database > 15) {
            $database = 0;
        }

        $parameters = [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'timeout' => self::CONNECT_TIMEOUT,
            'read_write_timeout' => self::READ_WRITE_TIMEOUT,
        ];
        if ($password !== '') {
            $parameters['password'] = $password;
        }

        $this->client = new \Predis\Client($parameters);

        return $this->client;
    }

    public function get(string|int $key): ?string
    {
        try {
            $client = $this->client();
            if ($client === null) {
                return null;
            }

            $value = $client->get($this->redisKey((string) $key));

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            $this->fail();

            return null;
        }
    }

    /**
     * Store a raw value. A positive TTL is required: the cache never writes
     * immortal keys.
     */
    public function set(string|int $key, string $value, int $expire = self::DEFAULT_TTL): bool
    {
        if ($expire <= 0 || strlen($value) > self::MAX_PAYLOAD_BYTES) {
            return false;
        }

        try {
            $client = $this->client();
            if ($client === null) {
                return false;
            }

            return $client->setex($this->redisKey((string) $key), $this->ttl($expire), $value)->getPayload() === 'OK';
        } catch (Throwable) {
            $this->fail();

            return false;
        }
    }

    /**
     * @return array<mixed>|null
     */
    public function getJson(string $key): ?array
    {
        $raw = $this->get($key);
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function setJson(string $key, mixed $value, int $expire = self::DEFAULT_TTL): bool
    {
        $raw = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($raw === false) {
            return false;
        }

        return $this->set($key, $raw, $expire);
    }

    /**
     * Get-or-set for array payloads. Empty results are not cached unless the
     * caller opts in, so unknown input cannot grow the keyspace.
     *
     * @param callable(): mixed $producer Producers must return an array.
     * @return array<mixed>
     */
    public function rememberJson(string $key, int $expire, callable $producer, bool $cacheEmpty = false): array
    {
        $cached = $this->getJson($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $producer();
        if (!is_array($value)) {
            throw new InvalidArgumentException('Cache producers must return an array.');
        }

        if ($value !== [] || $cacheEmpty) {
            $this->setJson($key, $value, $expire);
        }

        return $value;
    }

    public function delete(string|int $key): bool
    {
        try {
            $client = $this->client();
            if ($client === null) {
                return false;
            }

            return (int) $client->del($this->redisKey((string) $key)) > 0;
        } catch (Throwable) {
            $this->fail();

            return false;
        }
    }

    /**
     * Current generation of a namespace (0 until the first bump). Resolved once
     * per request so several keys cost a single Redis round trip.
     */
    public function version(string $namespace): int
    {
        if (array_key_exists($namespace, $this->versions)) {
            return $this->versions[$namespace];
        }

        $raw = $this->get($namespace . ':version');
        $version = ($raw !== null && ctype_digit($raw)) ? (int) $raw : self::VERSION_FALLBACK;
        $this->versions[$namespace] = $version;

        return $version;
    }

    /**
     * Compose a namespaced key. Bumping the namespace orphans every key of the
     * previous generation in O(1), avoiding SCAN and repopulation races.
     */
    public function namespaced(string $namespace, string $entity, string $discriminator = ''): string
    {
        $key = $namespace . ':g' . $this->version($namespace) . ':' . $entity;
        $discriminator = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $discriminator);

        return $discriminator === '' ? $key : $key . ':' . substr($discriminator, 0, 64);
    }

    /**
     * Invalidate a whole namespace with a single INCR.
     */
    public function bump(string $namespace): bool
    {
        try {
            $client = $this->client();
            if ($client === null) {
                return false;
            }

            $client->incr($this->redisKey($namespace . ':version'));
            unset($this->versions[$namespace]);

            return true;
        } catch (Throwable) {
            $this->fail();

            return false;
        }
    }

    /**
     * Fail-open for the rest of this request only; the next request gets a
     * fresh instance and retries Redis.
     */
    private function fail(): void
    {
        $this->disabled = true;
        $this->client = null;
    }

    private function prefix(): string
    {
        if ($this->prefix === null) {
            $prefix = (string) preg_replace(
                '/[^A-Za-z0-9:_\-]/',
                '',
                trim((string) ($_SERVER['REDIS_SERVER_PREFIX'] ?? $_ENV['REDIS_SERVER_PREFIX'] ?? ''))
            );
            if ($prefix === '') {
                $prefix = self::DEFAULT_PREFIX;
            }
            if (!str_ends_with($prefix, ':')) {
                $prefix .= ':';
            }

            $this->prefix = $prefix;
        }

        return $this->prefix;
    }

    private function redisKey(string $key): string
    {
        return $this->prefix() . $key;
    }

    /**
     * Nominal TTL with +/-10% jitter so keys written around the same time do
     * not expire in one synchronized wave against the database.
     */
    private function ttl(int $expire): int
    {
        if ($expire <= 1) {
            return max(1, $expire);
        }

        $jitter = max(1, (int) ceil($expire * self::JITTER_PERCENT / 100));

        return max(1, $expire + random_int(-$jitter, $jitter));
    }
}
