<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response;

/**
 * GitLab Repository Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
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
     *     name: non-empty-string,
     *     name_with_namespace: non-empty-string,
     *     description: string|null,
     *     web_url: non-empty-string,
     *     visibility: string,
     *     created_at: string,
     *     updated_at: string
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            fullName: $data['name_with_namespace'],
            description: $data['description'] ?? '',
            htmlUrl: $data['web_url'],
            private: $data['visibility'] === 'private',
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );
    }
}
