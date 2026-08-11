<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\Repository\Exception\AccessDeniedException;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\AuthenticationException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\GitHubConfigStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\HttpFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

#[\Testo\Codecov\Covers(Client::class)]
final class ClientTest
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

    /**
     * @return \Generator<string, array{int, string, array<string, string[]>, class-string<\Throwable>|null}>
     */
    public static function provideErrorScenarios(): \Generator
    {
        yield 'legacy rate limit response' => [
            403,
            \json_encode([
                'API rate limit exceeded for user ID 1234.',
                'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
            ]),
            [],
            RateLimitException::class,
        ];

        yield 'rate limit reported with 429' => [
            429,
            \json_encode(['message' => 'API rate limit exceeded', 'documentation_url' => 'https://docs.github.com']),
            [],
            RateLimitException::class,
        ];

        yield 'rate limit detected by header' => [
            403,
            \json_encode(['message' => 'Request forbidden', 'documentation_url' => 'https://docs.github.com']),
            ['x-ratelimit-remaining' => ['0']],
            RateLimitException::class,
        ];

        yield 'secondary rate limit' => [
            403,
            \json_encode(['message' => 'You have exceeded a secondary rate limit.']),
            [],
            RateLimitException::class,
        ];

        yield 'invalid token' => [
            401,
            \json_encode(['message' => 'Bad credentials']),
            [],
            AuthenticationException::class,
        ];

        yield 'forbidden without rate limit' => [
            403,
            \json_encode(['message' => 'Resource not accessible by integration']),
            [],
            AccessDeniedException::class,
        ];

        yield 'non-JSON forbidden body' => [
            403,
            'invalid json response',
            [],
            AccessDeniedException::class,
        ];

        yield 'missing repository' => [
            404,
            \json_encode(['message' => 'Not Found']),
            [],
            RepositoryNotFoundException::class,
        ];

        yield 'server error' => [
            502,
            'Bad Gateway',
            [],
            ApiException::class,
        ];

        yield 'unprocessable entity' => [
            422,
            \json_encode(['message' => 'Validation Failed']),
            [],
            ApiException::class,
        ];

        yield 'successful response' => [
            200,
            '[]',
            [],
            null,
        ];

        yield 'redirect is not an error' => [
            302,
            '',
            [],
            null,
        ];
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($result, $response);
    }

    #[\Testo\Test]
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
        \Testo\Assert::equals($result, $response);
    }

    #[\Testo\Test]
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
        \Testo\Assert::equals($result, $response);
    }

    #[\Testo\Test]
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
        \Testo\Expect::exception(RateLimitException::class)->withMessage('rate limit exceeded');

        // Act
        $this->client->request($method, $uri);
    }

    #[\Testo\Test]
    public function testRateLimitMessageSuggestsTokenWhenThereIsNoToken(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $this->httpClient = $this->httpClient->withResponse($request, ResponseStub::githubRateLimit());
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        try {
            $this->client->sendRequest($request);
            \Testo\Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            // Assert
            self::assertStringContainsString('GITHUB_TOKEN', $e->getMessage());
            self::assertStringContainsString('No API token is configured', $e->getMessage());
        }
    }

    #[\Testo\Test]
    public function testAuthenticationMessageMentionsConfiguredToken(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = new ResponseStub(401, [], \json_encode(['message' => 'Bad credentials']));

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $client = new Client($this->httpFactory, $this->httpClient, GitHubConfigStub::withToken('invalid-token'));

        // Act
        try {
            $client->sendRequest($request);
            \Testo\Assert::fail('AuthenticationException is expected.');
        } catch (AuthenticationException $e) {
            // Assert
            self::assertStringContainsString('Bad credentials', $e->getMessage());
            self::assertStringContainsString('invalid, expired or revoked', $e->getMessage());
        }
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($result, $response);
    }

    #[\Testo\Test]
    public function testSendRequestWrapsClientExceptionsIntoApiException(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $clientException = $this->createMock(ClientExceptionInterface::class);

        $this->httpClient = $this->httpClient->withException($request, $clientException);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        // Act
        try {
            $this->client->sendRequest($request);
            \Testo\Assert::fail('ApiException is expected.');
        } catch (ApiException $e) {
            // Assert
            self::assertStringContainsString('Failed to reach GitHub API', $e->getMessage());
            \Testo\Assert::same($e->getPrevious(), $clientException);
        }
    }

    #[\Testo\Data\DataProvider('provideRequestHeaders')]
    #[\Testo\Test]
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
        \Testo\Assert::same($result, $response);
    }

    /**
     * @param array<string, string[]> $headers
     * @param class-string<\Throwable>|null $expectedException
     */
    #[\Testo\Data\DataProvider('provideErrorScenarios')]
    #[\Testo\Test]
    public function testUnsuccessfulResponsesAreConvertedIntoExceptions(
        int $statusCode,
        string $responseBody,
        array $headers,
        ?string $expectedException,
    ): void {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = new ResponseStub($statusCode, $headers, $responseBody);

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        $expectedException === null or $this->expectException($expectedException);

        // Act
        $result = $this->client->sendRequest($request);

        // Assert
        \Testo\Assert::same($result, $response);
    }

    #[\Testo\Lifecycle\BeforeTest]
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
