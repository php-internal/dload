<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Composer\Semver\VersionParser;
use Internal\DLoad\Module\Repository\Internal\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Service\Destroyable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-import-type GitHubAssetApiResponse from GitHubAsset
 *
 * @psalm-type GitHubReleaseApiResponse = array{
 *     name: non-empty-string,
 *     assets: array<array-key, GitHubAssetApiResponse>
 * }
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\GitHub
 */
final class GitHubRelease extends Release implements Destroyable
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $version
     */
    public function __construct(
        private HttpClientInterface $client,
        GitHubRepository $repository,
        string $name,
        string $version,
    ) {
        parent::__construct($repository, $name, $version);
    }

    /**
     * @param GitHubReleaseApiResponse $data
     */
    public static function fromApiResponse(GitHubRepository $repository, HttpClientInterface $client, array $data): self
    {
        isset($data['name']) or throw new \InvalidArgumentException(
            'Passed array must contain "name" value of type string.',
        );

        $name = self::getTagName($data);
        $version = $data['tag_name'] ?? $data['name'];
        $result = new self($client, $repository, $name, $version);

        $result->assets = AssetsCollection::from(static function () use ($client, $result, $data): \Generator {
            /** @var GitHubAssetApiResponse $item */
            foreach ($data['assets'] ?? [] as $item) {
                yield GitHubAsset::fromApiResponse($client, $result, $item);
            }
        });
        return $result;
    }

    /**
     * @return non-empty-string
     * @throws ExceptionInterface
     */
    public function getConfig(): string
    {
        $config = \vsprintf('https://raw.githubusercontent.com/%s/%s/.rr.yaml', [
            $this->getRepository()->getName(),
            $this->getVersion(),
        ]);

        return $this->client->request('GET', $config)->getContent();
    }

    public function destroy(): void
    {
        $this->assets === null or \array_walk($this->assets, static fn(object $asset) =>
            $asset instanceof Destroyable and $asset->destroy());

        unset($this->assets, $this->repository, $this->client);
    }

    /**
     * Returns pretty-formatted tag (release) name.
     *
     * @note The return value is "pretty", but that does not mean that the tag physically exists.
     *
     * @param array{tag_name: string, name: string} $data
     * @return string
     */
    private static function getTagName(array $data): string
    {
        $parser = new VersionParser();

        try {
            return $parser->normalize($data['tag_name']);
        } catch (\Throwable $e) {
            try {
                return $parser->normalize($data['name']);
            } catch (\Throwable $e) {
                return 'dev-' . $data['tag_name'];
            }
        }
    }
}