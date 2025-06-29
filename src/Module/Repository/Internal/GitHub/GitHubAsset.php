<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Internal\Asset;
use Internal\DLoad\Service\Destroyable;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @psalm-type GitHubAssetApiResponse = array{
 *      name: non-empty-string,
 *      browser_download_url: non-empty-string
 * }
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubAsset extends Asset implements Destroyable
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    public function __construct(
        private readonly HttpFactory $httpFactory,
        private ClientInterface $client,
        GitHubRelease $release,
        string $name,
        string $uri,
    ) {
        parent::__construct(
            release: $release,
            name: $name,
            uri: $uri,
            os: OperatingSystem::tryFromBuildName($name),
            arch: Architecture::tryFromBuildName($name),
        );
    }

    /**
     * @param GitHubAssetApiResponse $data
     */
    public static function fromApiResponse(
        HttpFactory $httpFactory,
        ClientInterface $client,
        GitHubRelease $release,
        array $data,
    ): self {
        // Validate name
        \is_string($data['name'] ?? null) or throw new \InvalidArgumentException(
            'Passed array must contain "name" value of type string.',
        );

        // Validate uri
        \is_string($data['browser_download_url'] ?? null) or throw new \InvalidArgumentException(
            'Passed array must contain "browser_download_url" key of type string.',
        );

        return new self($httpFactory, $client, $release, $data['name'], $data['browser_download_url']);
    }

    /**
     * @param null|\Closure(int $dlNow, int|null $dlSize, array $info): mixed $progress
     *        throwing any exceptions MUST abort the request;
     *        it MUST be called on DNS resolution, on arrival of headers and on completion;
     *        it SHOULD be called on upload/download of data and at least 1/s
     *
     * @return \Generator<int, string, mixed, void>
     * @throws ClientExceptionInterface
     */
    public function download(?\Closure $progress = null): \Generator
    {
        $request = $this->httpFactory->request(Method::Get, $this->getUri());
        $response = $this->client->sendRequest($request);

        $body = $response->getBody();
        $size = $body->getSize();
        $loaded = 0;

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $loaded += \strlen($chunk);
            $progress === null or $progress($loaded, $size, []);
            yield $chunk;
        }
    }

    public function destroy(): void
    {
        unset($this->release, $this->client);
    }
}
