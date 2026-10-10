<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Repository\Exception\AccessDeniedException;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\AuthenticationException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\ApiToken;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\Server;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientExceptionStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ClientStub;
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
    private ?ApiToken $token;
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
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

        $result = $this->client->request($method, $uri);

        Assert::same($result, $response);
    }

    #[Test]
    public function requestWithAuthTokenAddsAuthorizationHeader(): void
    {
        $token = new ApiToken('github_pat_test_token_123', 'GITHUB_TOKEN');
        $method = 'GET';
        $uri = \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $response = ResponseStub::ok();

        $this->httpClient = $this->httpClient->withResponse($request, $response);

        $clientWithToken = new Client($this->httpFactory, $this->httpClient, $token);

        $result = $clientWithToken->request($method, $uri);

        Assert::equals($result, $response);
    }

    #[Test]
    public function tokenIsSentToGitHubHostsOnly(): void
    {
        // Asset URLs may come from a registry file on disk, so the token must not follow them anywhere
        $http = new ClientStub();
        $client = new Client(new NyholmFactoryImpl(new Logger()), $http, new ApiToken('secret', 'GITHUB_TOKEN'));

        $client->request('GET', 'https://api.github.com/repos/owner/repo/releases');
        $client->request('GET', 'https://objects.githubusercontent.com/asset');
        $client->request('GET', 'https://GitHub.com/owner/repo/releases/download/v1/rr.tar.gz');
        $client->request('GET', 'https://evil.example/github.com/asset');
        $client->request('GET', 'https://notgithub.com/asset');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[1]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[2]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[3]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[4]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[3]->getHeaderLine('accept'), 'application/vnd.github.v3+json');
    }

    #[Test]
    public function tokenIsNotSentToPublicGitHubOverPlainHttp(): void
    {
        $http = new ClientStub();
        $client = new Client(new NyholmFactoryImpl(new Logger()), $http, new ApiToken('secret', 'GITHUB_TOKEN'));

        $client->request('GET', 'http://github.com/owner/repo/releases/download/v1/rr.tar.gz');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), '');
    }

    #[Test]
    public function enterpriseTokenIsSentToItsServerOnly(): void
    {
        $http = new ClientStub();
        $client = new Client(
            new NyholmFactoryImpl(new Logger()),
            $http,
            new ApiToken('secret', 'DLOAD_TOKEN_GHE_EXAMPLE_COM'),
            Server::fromString('ghe.example.com'),
        );

        $client->request('GET', 'https://ghe.example.com/api/v3/repos/owner/repo/releases');
        $client->request('GET', 'https://ghe.example.com/owner/repo/releases/download/v1/rr.tar.gz');
        $client->request('GET', 'https://api.github.com/repos/owner/repo/releases');
        $client->request('GET', 'https://media.ghe.example.com/asset');
        $client->request('GET', 'http://ghe.example.com/owner/repo/releases/download/v1/rr.tar.gz');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[1]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[2]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[3]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[4]->getHeaderLine('authorization'), '');
    }

    #[Test]
    public function tokenIsNotSentToARemoteServerOverPlainHttp(): void
    {
        $http = new ClientStub();
        $client = new Client(
            new NyholmFactoryImpl(new Logger()),
            $http,
            new ApiToken('secret', 'DLOAD_TOKEN_GHE_EXAMPLE_COM'),
            Server::fromString('http://ghe.example.com'),
        );

        $client->request('GET', 'http://ghe.example.com/api/v3/repos/owner/repo/releases');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), '');
    }

    #[Test]
    public function enterpriseErrorsNameTheVariableOfTheServer(): never
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $this->httpClient = $this->httpClient->withResponse($request, new ResponseStub(401, [], '{"message":"Bad credentials"}'));
        $client = new Client($this->httpFactory, $this->httpClient, null, Server::fromString('ghe.example.com'));

        Expect::exception(AuthenticationException::class)->withMessageContaining('DLOAD_TOKEN_GHE_EXAMPLE_COM');

        $client->sendRequest($request);
    }

    #[Test]
    public function errorsNameTheVariableTheTokenCameFrom(): never
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $this->httpClient = $this->httpClient->withResponse($request, new ResponseStub(401, [], '{"message":"Bad credentials"}'));
        $client = new Client($this->httpFactory, $this->httpClient, new ApiToken('bad', 'DLOAD_TOKEN_GITHUB_COM'));

        Expect::exception(AuthenticationException::class)->withMessageContaining('DLOAD_TOKEN_GITHUB_COM');

        $client->sendRequest($request);
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
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

        Expect::exception(RateLimitException::class)->withMessageContaining('rate limit exceeded');

        $this->client->request($method, $uri);
    }

    #[Test]
    public function rateLimitMessageSuggestsTokenWhenThereIsNoToken(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $this->httpClient = $this->httpClient->withResponse($request, ResponseStub::githubRateLimit());
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

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
        $client = new Client($this->httpFactory, $this->httpClient, new ApiToken('invalid-token', 'GITHUB_TOKEN'));

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
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

        $result = $this->client->sendRequest($request);

        Assert::same($result, $response);
    }

    #[Test]
    public function sendRequestWrapsClientExceptionsIntoApiException(): void
    {
        $request = \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
        $clientException = new ClientExceptionStub('connection reset');

        $this->httpClient = $this->httpClient->withException($request, $clientException);
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

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
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

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
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);

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
        $this->token = null;
        $this->client = new Client($this->httpFactory, $this->httpClient, $this->token);
    }
}
