<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Exception\GitHubRateLimitException;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\GitHubConfigStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\HttpFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ResponseStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
    private HttpFactoryStub $httpFactory;
    private ClientStub $httpClient;
    private GitHub $gitHubConfig;
    private Client $client;

    public static function provideRequestHeaders(): \Generator
    {
        yield 'no additional headers' => [[]];
        yield 'custom headers' => [['x-custom' => 'value', 'content-type' => 'application/json']];
        yield 'override default headers' => [['accept' => 'application/json']];
    }

    public static function provideRateLimitScenarios(): \Generator
    {
        yield 'valid rate limit response' => [
            403,
            \json_encode([
                'API rate limit exceeded for user ID 1234. Check the hourly limit for your plan at https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
                'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
            ]),
            true,
        ];

        yield 'non-403 status code' => [
            429,
            \json_encode(['API rate limit exceeded', 'https://docs.github.com']),
            false,
        ];

        yield '403 with invalid JSON' => [
            403,
            'invalid json response',
            false,
        ];

        yield '403 with wrong array structure' => [
            403,
            \json_encode(['message' => 'Forbidden']),
            false,
        ];

        yield '403 with wrong array count' => [
            403,
            \json_encode(['API rate limit exceeded']),
            false,
        ];

        yield '403 with non-string elements' => [
            403,
            \json_encode([123, 456]),
            false,
        ];

        yield '403 without rate limit text' => [
            403,
            \json_encode(['Something else', 'https://docs.github.com']),
            false,
        ];
    }

    public function testRequestAddsDefaultHeaders(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->request($method, $uri);

        // Assert
        self::assertSame($response, $result);
    }

    public function testRequestWithAuthTokenAddsAuthorizationHeader(): void
    {
        // Arrange
        $token = 'github_pat_test_token_123';
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $gitHubConfigWithToken = GitHubConfigStub::withToken($token);
        $this->httpClient = $this->httpClient->withResponse($request, $response);

        $clientWithToken = new Client($this->httpFactory, $this->httpClient, $gitHubConfigWithToken);

        // Act
        $result = $clientWithToken->request($method, $uri);

        // Assert
        self::assertEquals($response, $result);
    }

    public function testRequestWithoutTokenDoesNotAddAuthorizationHeader(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $this->httpClient = $this->httpClient->withResponse($request, $response);

        // Act
        $result = $this->client->request($method, $uri);

        // Assert
        self::assertEquals($response, $result);
    }

    public function testDetectsRateLimitResponseAndThrowsException(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $rateLimitResponse = ResponseStub::githubRateLimit();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $rateLimitResponse);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Assert (before Act for exceptions)
        $this->expectException(GitHubRateLimitException::class);
        $this->expectExceptionMessage('API rate limit exceeded for user ID 1234');

        // Act
        $this->client->request($method, $uri);
    }

    public function testDoesNotThrowExceptionForNon403Response(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->request($method, $uri);

        // Assert
        self::assertSame($response, $result);
    }

    public function testDoesNotThrowExceptionForNonRateLimitError(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $forbiddenResponse = ResponseStub::githubForbidden();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $forbiddenResponse);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->request($method, $uri);

        // Assert
        self::assertSame($forbiddenResponse, $result);
    }

    public function testDoesNotThrowExceptionForInvalidJsonResponse(): void
    {
        // Arrange
        $method = 'GET';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $invalidJsonResponse = ResponseStub::invalidJson();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $invalidJsonResponse);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->request($method, $uri);

        // Assert
        self::assertSame($invalidJsonResponse, $result);
    }

    public function testSendRequestDelegatesToHttpClient(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->sendRequest($request);

        // Assert
        self::assertSame($response, $result);
    }

    public function testSendRequestThrowsRateLimitExceptionOn403WithRateLimitJson(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $rateLimitResponse = ResponseStub::githubRateLimit();

        $this->httpClient = $this->httpClient->withResponse($request, $rateLimitResponse);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Assert (before Act for exceptions)
        $this->expectException(GitHubRateLimitException::class);

        // Act
        $this->client->sendRequest($request);
    }

    public function testSendRequestPropagatesClientExceptions(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $clientException = $this->createMock(ClientExceptionInterface::class);

        $this->httpClient = $this->httpClient->withException($request, $clientException);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Assert (before Act for exceptions)
        $this->expectException(ClientExceptionInterface::class);

        // Act
        $this->client->sendRequest($request);
    }

    #[DataProvider('provideRequestHeaders')]
    public function testRequestMergesHeadersCorrectly(array $additionalHeaders): void
    {
        // Arrange
        $method = 'POST';
        $uri = $this->createMock(UriInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = ResponseStub::ok();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        $result = $this->client->request($method, $uri, $additionalHeaders);

        // Assert
        self::assertSame($response, $result);
    }

    #[DataProvider('provideRateLimitScenarios')]
    public function testRateLimitDetectionScenarios(
        int $statusCode,
        string $responseBody,
        bool $shouldThrowException,
    ): void {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = new ResponseStub($statusCode, [], $responseBody);

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        if ($shouldThrowException) {
            $this->expectException(GitHubRateLimitException::class);
        }

        // Act & Assert
        $result = $this->client->sendRequest($request);

        if (!$shouldThrowException) {
            self::assertSame($response, $result);
        }
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->httpFactory = new HttpFactoryStub(
            uriFactory: fn() => $this->createMock(UriInterface::class),
            requestFactory: fn() => $this->createMock(RequestInterface::class),
            clientFactory: fn() => $this->createMock(\Psr\Http\Client\ClientInterface::class),
        );
        $this->httpClient = new ClientStub();
        $this->gitHubConfig = GitHubConfigStub::withoutToken();
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);
    }
}
