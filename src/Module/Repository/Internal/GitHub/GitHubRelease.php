<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Composer\Semver\VersionParser;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Destroyable;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * GitHub Release class representing a release in a GitHub repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRelease extends Release implements Destroyable
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        private readonly RepositoryApi $api,
        GitHubRepository $repository,
        string $name,
        Version $version,
    ) {
        parent::__construct($repository, $name, $version);
    }

    public static function fromDTO(
        RepositoryApi $api,
        GitHubRepository $repository,
        ReleaseInfo $dto,
    ): self {
        $name = self::getTagName($dto->name, $dto->tagName);
        $version = Version::fromVersionString($dto->tagName);
        $result = new self($api, $repository, $name, $version);

        $result->assets = AssetsCollection::create(static function () use ($api, $result, $dto): \Generator {
            foreach ($dto->assets as $assetDTO) {
                yield GitHubAsset::fromDTO($api, $result, $assetDTO);
            }
        });

        return $result;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getConfig(): string
    {
        $configUrl = \vsprintf('https://raw.githubusercontent.com/%s/%s/.rr.yaml', [
            $this->getRepository()->getName(),
            $this->getVersion(),
        ]);

        $response = $this->api->request('GET', $configUrl);

        return $response->getBody()->__toString();
    }

    public function destroy(): void
    {
        $this->assets === null or $this->assets->map(
            static fn(object $asset) => $asset instanceof Destroyable and $asset->destroy(),
        );

        unset($this->assets, $this->repository);
    }

    /**
     * Returns pretty-formatted tag (release) name.
     *
     * @note The return value is "pretty", but that does not mean that the tag physically exists.
     */
    private static function getTagName(string $name, string $tagName): string
    {
        $parser = new VersionParser();

        try {
            return $parser->normalize($tagName);
        } catch (\Throwable) {
            try {
                return $parser->normalize($name);
            } catch (\Throwable) {
                return 'dev-' . $tagName;
            }
        }
    }
}
