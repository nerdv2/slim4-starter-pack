<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\DateFormat;
use App\DTO\Request\CustomerCreateRequest;
use App\DTO\Request\CustomerUpdateRequest;
use App\Exceptions\AppException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helper\CacheRedis;
use App\Helper\Pagination;
use App\Helper\UploadHelper;
use App\Model\CustomerModel;
use Psr\Http\Message\UploadedFileInterface;

final class CustomerService
{
    /**
     * List and statistics cache lifetime in seconds. Writes bump the namespace,
     * so this is only the upper bound for externally modified data.
     */
    private const int CACHE_TTL = 300;

    private const string CACHE_NAMESPACE = 'customer';

    /** @var list<string> */
    private const array AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly CustomerModel $customerModel,
        private readonly CacheRedis $cache,
        private readonly UploadHelper $uploadHelper
    ) {
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total_data: int, total_page: int}
     */
    public function list(string $keywords, ?string $status, int $page, int $limit): array
    {
        $cacheKey = $this->cache->namespaced(
            self::CACHE_NAMESPACE,
            'list',
            sha1(implode('|', [strtolower(trim($keywords)), (string) $status, $page, $limit]))
        );

        /** @var array{data: array<int, array<string, mixed>>, total_data: int, total_page: int} $result */
        $result = $this->cache->rememberJson(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($keywords, $status, $page, $limit): array {
                $totalData = $this->customerModel->countGet($keywords, $status);

                return [
                    'data' => array_map(
                        fn (object $row): array => $this->toPublicArray($row),
                        $this->customerModel->get($keywords, $status, $page, $limit)
                    ),
                    'total_data' => $totalData,
                    'total_page' => Pagination::totalPages($totalData, $limit),
                ];
            },
            cacheEmpty: true
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int|string $id): array
    {
        $customer = $this->customerModel->findById($id);
        if ($customer === null) {
            throw new NotFoundException('Customer not found.');
        }

        return $this->toPublicArray($customer);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(CustomerCreateRequest $dto): array
    {
        $this->assertUnique($dto->name, $dto->email);

        $id = $this->customerModel->create($dto->toArray());
        $this->cache->bump(self::CACHE_NAMESPACE);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(CustomerUpdateRequest $dto): array
    {
        if (!$this->customerModel->exists($dto->id)) {
            throw new NotFoundException('Customer not found.');
        }

        $this->assertUnique($dto->payload->name, $dto->payload->email, $dto->id);

        $this->customerModel->update($dto->id, $dto->payload->toArray());
        $this->cache->bump(self::CACHE_NAMESPACE);

        return $this->get($dto->id);
    }

    public function delete(int|string $id): void
    {
        $customer = $this->customerModel->findById($id);
        if ($customer === null) {
            throw new NotFoundException('Customer not found.');
        }

        $avatarPath = $this->avatarPath($customer);
        $this->customerModel->deleteById($id);
        if ($avatarPath !== null) {
            $this->uploadHelper->deleteUploadedFile($avatarPath);
        }
        $this->cache->bump(self::CACHE_NAMESPACE);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadAvatar(int|string $id, UploadedFileInterface $file): array
    {
        $customer = $this->customerModel->findById($id);
        if ($customer === null) {
            throw new NotFoundException('Customer not found.');
        }

        $this->assertAvatar($file);

        $path = $this->uploadHelper->moveUploadedFile($file, 'avatars');
        if ($path === false) {
            throw new AppException('The avatar could not be stored.');
        }

        $previous = $this->avatarPath($customer);
        $this->customerModel->update($id, ['avatar_path' => $path]);
        if ($previous !== null && $previous !== $path) {
            $this->uploadHelper->deleteUploadedFile($previous);
        }
        $this->cache->bump(self::CACHE_NAMESPACE);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeAvatar(int|string $id): array
    {
        $customer = $this->customerModel->findById($id);
        if ($customer === null) {
            throw new NotFoundException('Customer not found.');
        }

        $previous = $this->avatarPath($customer);
        $this->customerModel->update($id, ['avatar_path' => null]);
        if ($previous !== null) {
            $this->uploadHelper->deleteUploadedFile($previous);
        }
        $this->cache->bump(self::CACHE_NAMESPACE);

        return $this->get($id);
    }

    /**
     * Dashboard statistics: counts per status, total and new customers in the
     * last seven days.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $cacheKey = $this->cache->namespaced(self::CACHE_NAMESPACE, 'stats');

        /** @var array<string, mixed> $stats */
        $stats = $this->cache->rememberJson(
            $cacheKey,
            self::CACHE_TTL,
            function (): array {
                $counts = $this->customerModel->statusCounts();
                $byStatus = $counts;
                unset($byStatus['total']);

                return [
                    'total' => $counts['total'] ?? 0,
                    'by_status' => $byStatus,
                    'created_last_7_days' => $this->customerModel->countCreatedSince(
                        date(DateFormat::DATETIME, time() - (7 * 86400))
                    ),
                ];
            },
            cacheEmpty: true
        );

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(object $row): array
    {
        $avatarPath = $this->avatarPath($row);

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => $row->email !== null ? (string) $row->email : null,
            'phone' => $row->phone !== null ? (string) $row->phone : null,
            'company' => $row->company !== null ? (string) $row->company : null,
            'status' => (string) $row->status,
            'address' => $row->address !== null ? (string) $row->address : null,
            'notes' => $row->notes !== null ? (string) $row->notes : null,
            'avatar_url' => $avatarPath === null ? null : $this->publicUrl($avatarPath),
            'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
            'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
        ];
    }

    private function assertUnique(string $name, ?string $email, int|string|null $exceptId = null): void
    {
        $errors = [];

        if ($this->customerModel->existsByName($name, $exceptId)) {
            $errors['name'] = 'Customer name already exists.';
        }

        if ($email !== null && $this->customerModel->existsByEmail($email, $exceptId)) {
            $errors['email'] = 'Customer email already exists.';
        }

        if ($errors !== []) {
            throw new ValidationException('Validation failed.', $errors);
        }
    }

    private function assertAvatar(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Avatar upload failed.', [
                'avatar' => $this->uploadErrorMessage($file->getError()),
            ]);
        }

        $maxBytes = (int) ($_SERVER['UPLOAD_AVATAR_MAX_BYTES'] ?? $_ENV['UPLOAD_AVATAR_MAX_BYTES'] ?? 2097152);
        $size = $file->getSize();
        if ($size !== null && $size > $maxBytes) {
            throw new ValidationException('Avatar is too large.', [
                'avatar' => sprintf('Avatar must not exceed %d MB.', (int) ceil($maxBytes / 1048576)),
            ]);
        }

        $allowed = $this->allowedAvatarExtensions();
        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new ValidationException('Avatar type is not allowed.', [
                'avatar' => 'Allowed avatar extensions: ' . implode(', ', $allowed) . '.',
            ]);
        }

        // The client-provided extension is not proof; sniff local uploads too.
        $tmpPath = $file->getStream()->getMetadata('uri');
        if (is_string($tmpPath) && is_file($tmpPath)) {
            $mimeType = (string) (mime_content_type($tmpPath) ?: '');
            if ($mimeType !== '' && !in_array($mimeType, self::AVATAR_MIME_TYPES, true)) {
                throw new ValidationException('Avatar type is not allowed.', [
                    'avatar' => 'The uploaded file is not a JPEG, PNG or WebP image.',
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allowedAvatarExtensions(): array
    {
        $configured = (string) (
            $_SERVER['UPLOAD_AVATAR_EXTENSIONS'] ?? $_ENV['UPLOAD_AVATAR_EXTENSIONS'] ?? 'jpg,jpeg,png,webp'
        );

        $extensions = array_values(array_filter(array_map(
            static fn (string $extension): string => strtolower(trim($extension)),
            explode(',', $configured)
        )));

        return $extensions === [] ? ['jpg', 'jpeg', 'png', 'webp'] : $extensions;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the size limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted; please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'The upload failed; please try again.',
        };
    }

    private function avatarPath(object $row): ?string
    {
        $path = $row->avatar_path ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function publicUrl(string $relativePath): string
    {
        $baseUrl = rtrim((string) (
            $_SERVER['APP_BASE_URL'] ?? $_ENV['APP_BASE_URL'] ?? ''
        ), '/');

        return ($baseUrl === '' ? '' : $baseUrl . '/') . ltrim($relativePath, '/');
    }
}
