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
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientExceptionStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\GitHubConfigStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\HttpFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(Client::class)]
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

    #[Test]
    public function requestAddsDefaultHeaders(): void
    {
        $method = 'GET';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        $result = $this->client->request($method, $uri);

        Assert::same($result, $response);
    }

    #[Test]
    public function requestWithAuthTokenAddsAuthorizationHeader(): void
    {
        $token = 'github_pat_test_token_123';
        $method = 'GET';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $gitHubConfigWithToken = GitHubConfigStub::withToken($token);
        $this->httpClient = $this->httpClient->withResponse($request, $response);

        $clientWithToken = new Client($this->httpFactory, $this->httpClient, $gitHubConfigWithToken);

        $result = $clientWithToken->request($method, $uri);

        Assert::equals($result, $response);
    }

    #[Test]
    public function requestWithoutTokenDoesNotAddAuthorizationHeader(): void
    {
        $method = 'GET';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $this->httpClient = $this->httpClient->withResponse($request, $response);

        $result = $this->client->request($method, $uri);

        Assert::equals($result, $response);
    }

    #[Test]
    public function detectsRateLimitResponseAndThrowsException(): void
    {
        $method = 'GET';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $rateLimitResponse = ResponseStub::githubRateLimit();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $rateLimitResponse);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        Expect::exception(RateLimitException::class)->withMessageContaining('rate limit exceeded');

        $this->client->request($method, $uri);
    }

    #[Test]
    public function rateLimitMessageSuggestsTokenWhenThereIsNoToken(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $this->httpClient = $this->httpClient->withResponse($request, ResponseStub::githubRateLimit());
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        try {
            $this->client->sendRequest($request);
            Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            Assert::string($e->getMessage())->contains('GITHUB_TOKEN');
            Assert::string($e->getMessage())->contains('No API token is configured');
        }
    }

    #[Test]
    public function authenticationMessageMentionsConfiguredToken(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = new ResponseStub(401, [], \json_encode(['message' => 'Bad credentials']));

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $client = new Client($this->httpFactory, $this->httpClient, GitHubConfigStub::withToken('invalid-token'));

        try {
            $client->sendRequest($request);
            Assert::fail('AuthenticationException is expected.');
        } catch (AuthenticationException $e) {
            Assert::string($e->getMessage())->contains('Bad credentials');
            Assert::string($e->getMessage())->contains('invalid, expired or revoked');
        }
    }

    #[Test]
    public function sendRequestDelegatesToHttpClient(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        $result = $this->client->sendRequest($request);

        Assert::same($result, $response);
    }

    #[Test]
    public function sendRequestWrapsClientExceptionsIntoApiException(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $clientException = new ClientExceptionStub('connection reset');

        $this->httpClient = $this->httpClient->withException($request, $clientException);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        try {
            $this->client->sendRequest($request);
            Assert::fail('ApiException is expected.');
        } catch (ApiException $e) {
            Assert::string($e->getMessage())->contains('Failed to reach GitHub API');
            Assert::same($e->getPrevious(), $clientException);
        }
    }

    #[DataProvider('provideRequestHeaders')]
    #[Test]
    public function requestMergesHeadersCorrectly(array $additionalHeaders): void
    {
        $method = 'POST';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $this->httpFactory = $this->httpFactory->withRequest($method, $uri, $request);
        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        $result = $this->client->request($method, $uri, $additionalHeaders);

        Assert::same($result, $response);
    }

    /**
     * @param array<string, string[]> $headers
     * @param class-string<\Throwable>|null $expectedException
     */
    #[DataProvider('provideErrorScenarios')]
    #[Test]
    public function unsuccessfulResponsesAreConvertedIntoExceptions(
        int $statusCode,
        string $responseBody,
        array $headers,
        ?string $expectedException,
    ): void {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = new ResponseStub($statusCode, $headers, $responseBody);

        $this->httpClient = $this->httpClient->withResponse($request, $response);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);

        $expectedException === null or Expect::exception($expectedException);

        $result = $this->client->sendRequest($request);

        Assert::same($result, $response);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->httpFactory = new HttpFactoryStub(
            uriFactory: static fn() => \Mockery::mock(UriInterface::class)->shouldIgnoreMissing(),
            requestFactory: static fn() => \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing(),
            clientFactory: static fn() => \Mockery::mock(ClientInterface::class)->shouldIgnoreMissing(),
        );
        $this->httpClient = new ClientStub();
        $this->gitHubConfig = GitHubConfigStub::withoutToken();
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->gitHubConfig);
    }
}
