<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Composer\Semver\VersionParser;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Destroyable;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

/**
 * @psalm-import-type GitHubAssetApiResponse from GitHubAsset
 *
 * @psalm-type GitHubReleaseApiResponse = array{
 *     name: string|null,
 *     tag-name: string|null,
 *     assets: array<array-key, GitHubAssetApiResponse>
 * }
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRelease extends Release implements Destroyable
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        private readonly HttpFactory $httpFactory,
        private ClientInterface $client,
        GitHubRepository $repository,
        string $name,
        Version $version,
    ) {
        parent::__construct($repository, $name, $version);
    }

    /**
     * @param GitHubReleaseApiResponse $data
     */
    public static function fromApiResponse(
        GitHubRepository $repository,
        HttpFactory $httpFactory,
        ClientInterface $client,
        array $data,
    ): self {
        isset($data['tag_name']) || isset($data['name']) or throw new \InvalidArgumentException(
            'Passed array must contain "tag_name" value of type string.',
        );

        $name = self::getTagName($data);
        $version = $data['tag_name'] ?? (string) $data['name'];
        $result = new self($httpFactory, $client, $repository, $name, Version::fromVersionString($version));

        $result->assets = AssetsCollection::create(static function () use ($client, $result, $data, $httpFactory): \Generator {
            /** @var GitHubAssetApiResponse $item */
            foreach ($data['assets'] ?? [] as $item) {
                yield GitHubAsset::fromApiResponse($httpFactory, $client, $result, $item);
            }
        });
        return $result;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getConfig(): string
    {
        $request = $this->httpFactory->request(
            Method::Get,
            \vsprintf('https://raw.githubusercontent.com/%s/%s/.rr.yaml', [
                $this->getRepository()->getName(),
                $this->getVersion(),
            ]),
        );

        return $this->client->sendRequest($request)->getBody()->__toString();
    }

    public function destroy(): void
    {
        $this->assets === null or $this->assets->map(
            static fn(object $asset) => $asset instanceof Destroyable and $asset->destroy(),
        );

        unset($this->assets, $this->repository, $this->client);
    }

    /**
     * Returns pretty-formatted tag (release) name.
     *
     * @note The return value is "pretty", but that does not mean that the tag physically exists.
     *
     * @param array{tag_name: string|null, name: string|null} $data
     */
    private static function getTagName(array $data): string
    {
        $parser = new VersionParser();

        try {
            return $parser->normalize($data['tag_name']);
        } catch (\Throwable) {
            try {
                return $parser->normalize((string) $data['name']);
            } catch (\Throwable) {
                return 'dev-' . $data['tag_name'];
            }
        }
    }
}
