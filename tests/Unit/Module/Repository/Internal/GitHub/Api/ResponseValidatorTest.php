<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Repository\Exception\AccessDeniedException;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\ResponseValidator;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[\Testo\Codecov\Covers(ResponseValidator::class)]
#[\Testo\Codecov\Covers(\Internal\DLoad\Module\Repository\Internal\ResponseValidator::class)]
final class ResponseValidatorTest
{
    #[\Testo\Test]
    public function testSuccessfulResponsePassesValidation(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);

        // Act
        $validator->validate(self::releasesRequest(), ResponseStub::ok('[]'));

        // Assert
        \Testo\Assert::true(true, 'Successful responses must not throw.');
    }

    #[\Testo\Test]
    public function testRateLimitWithoutTokenExplainsAnonymousLimit(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(
            403,
            ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-limit' => ['60']],
            \json_encode(['message' => 'API rate limit exceeded for 1.2.3.4.']),
        );

        // Assert (before Act for exceptions)
        \Testo\Expect::exception(RateLimitException::class)->withMessage('60 requests per hour');

        // Act
        $validator->validate(self::releasesRequest(), $response);
    }

    #[\Testo\Test]
    public function testRateLimitWithTokenReportsSpentQuotaAndResetTime(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: true);
        $resetsAt = \time() + 600;
        $response = new ResponseStub(
            429,
            ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => [(string) $resetsAt]],
            \json_encode(['message' => 'API rate limit exceeded']),
        );

        // Act
        try {
            $validator->validate(self::releasesRequest(), $response);
            \Testo\Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            // Assert
            self::assertStringContainsString('spent its quota', $e->getMessage());
            self::assertStringContainsString('GITHUB_TOKEN', $e->getMessage());
            \Testo\Assert::notNull($e->resetAt);
            \Testo\Assert::same($e->resetAt->getTimestamp(), $resetsAt);
        }
    }

    #[\Testo\Test]
    public function testSecondaryRateLimitIsRecognized(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: true);
        $response = new ResponseStub(
            403,
            ['retry-after' => ['60']],
            \json_encode(['message' => 'You have exceeded a secondary rate limit. Please wait a few minutes.']),
        );

        // Assert (before Act for exceptions)
        \Testo\Expect::exception(RateLimitException::class)->withMessage('secondary rate limit exceeded');

        // Act
        $validator->validate(self::releasesRequest(), $response);
    }

    #[\Testo\Test]
    public function testForbiddenResponseMentionsRepositoryAndToken(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: true);
        $response = new ResponseStub(403, [], \json_encode(['message' => 'Resource not accessible by integration']));

        // Act
        try {
            $validator->validate(self::releasesRequest(), $response);
            \Testo\Assert::fail('AccessDeniedException is expected.');
        } catch (AccessDeniedException $e) {
            // Assert
            \Testo\Assert::same($e->repository, 'owner/repo');
            self::assertStringContainsString('Resource not accessible by integration', $e->getMessage());
            self::assertStringContainsString('no read access to this repository', $e->getMessage());
        }
    }

    #[\Testo\Test]
    public function testNotFoundResponseSuggestsCheckingRepositoryAddress(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(404, [], \json_encode(['message' => 'Not Found']));

        // Act
        try {
            $validator->validate(self::releasesRequest(), $response);
            \Testo\Assert::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            // Assert
            self::assertStringContainsString('repository `owner/repo`', $e->getMessage());
            self::assertStringContainsString('GITHUB_TOKEN', $e->getMessage());
        }
    }

    #[\Testo\Test]
    public function testServerErrorIsReportedAsTemporaryFailure(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(503, [], 'Service Unavailable', 'Service Unavailable');

        // Assert (before Act for exceptions)
        \Testo\Expect::exception(ApiException::class)->withMessage('GitHub API is unavailable: HTTP 503');

        // Act
        $validator->validate(self::releasesRequest(), $response);
    }

    #[\Testo\Test]
    public function testRepositoryIsResolvedFromAssetDownloadUrl(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://github.com/owner/repo/releases/download/v1.0.0/asset.zip');
        $response = new ResponseStub(404, [], \json_encode(['message' => 'Not Found']));

        // Act
        try {
            $validator->validate($request, $response);
            \Testo\Assert::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            // Assert
            \Testo\Assert::same($e->repository, 'owner/repo');
        }
    }

    #[\Testo\Test]
    public function testTransportFailureKeepsTheOriginalError(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $original = new \RuntimeException('Could not resolve host: api.github.com');

        // Act
        $exception = $validator->transportFailure(self::releasesRequest(), $original);

        // Assert
        self::assertStringContainsString('Failed to reach GitHub API', $exception->getMessage());
        self::assertStringContainsString('Could not resolve host', $exception->getMessage());
        \Testo\Assert::same($exception->getPrevious(), $original);
        \Testo\Assert::same($exception->repository, 'owner/repo');
    }

    #[\Testo\Test]
    public function testLongApiMessageIsTruncatedWithoutBreakingUtf8(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        // An ASCII prefix shifts the byte-based cut into the middle of a multibyte character
        $apiMessage = 'x' . \str_repeat('я', 400);
        $response = new ResponseStub(422, [], \json_encode(['message' => $apiMessage]));

        // Act
        try {
            $validator->validate(self::releasesRequest(), $response);
            \Testo\Assert::fail('ApiException is expected.');
        } catch (ApiException $e) {
            // Assert
            $message = $e->getMessage();
            \Testo\Assert::same(\preg_match('//u', $message), 1, 'The message must stay valid UTF-8.');
            self::assertStringContainsString('…', $message);
        }
    }

    private static function releasesRequest(): RequestInterface
    {
        return new Request('GET', 'https://api.github.com/repos/owner/repo/releases?page=1');
    }
}
