<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response;

/**
 * GitHub Repository Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class RepositoryInfo
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $fullName
     * @param non-empty-string $htmlUrl
     */
    public function __construct(
        public readonly string $name,
        public readonly string $fullName,
        public readonly string $description,
        public readonly string $htmlUrl,
        public readonly bool $private,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     full_name: string,
     *     description: string|null,
     *     html_url: string,
     *     private: bool,
     *     created_at: string,
     *     updated_at: string
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            fullName: $data['full_name'],
            description: $data['description'] ?? '',
            htmlUrl: $data['html_url'],
            private: $data['private'],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );
    }
}
