<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Repository\Exception\AccessDeniedException;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\AssetNotFoundException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\ResponseValidator;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Nyholm\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(ResponseValidator::class)]
#[Covers(\Internal\DLoad\Module\Repository\Internal\ResponseValidator::class)]
final class ResponseValidatorTest
{
    #[Test]
    public function successfulResponsePassesValidation(): void
    {
        $validator = new ResponseValidator(authenticated: false);

        $validator->validate(self::releasesRequest(), ResponseStub::ok('[]'));

        Assert::true(true, 'Successful responses must not throw.');
    }

    #[Test]
    public function rateLimitWithoutTokenExplainsAnonymousLimit(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(
            403,
            ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-limit' => ['60']],
            \json_encode(['message' => 'API rate limit exceeded for 1.2.3.4.']),
        );

        Expect::exception(RateLimitException::class)->withMessageContaining('60 requests per hour');

        $validator->validate(self::releasesRequest(), $response);
    }

    #[Test]
    public function rateLimitWithTokenReportsSpentQuotaAndResetTime(): void
    {
        $validator = new ResponseValidator(authenticated: true);
        $resetsAt = \time() + 600;
        $response = new ResponseStub(
            429,
            ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => [(string) $resetsAt]],
            \json_encode(['message' => 'API rate limit exceeded']),
        );

        try {
            $validator->validate(self::releasesRequest(), $response);
            Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            Assert::string($e->getMessage())->contains('spent its quota');
            Assert::string($e->getMessage())->contains('GITHUB_TOKEN');
            Assert::notNull($e->resetAt);
            Assert::same($e->resetAt->getTimestamp(), $resetsAt);
        }
    }

    #[Test]
    public function secondaryRateLimitIsRecognized(): void
    {
        $validator = new ResponseValidator(authenticated: true);
        $response = new ResponseStub(
            403,
            ['retry-after' => ['60']],
            \json_encode(['message' => 'You have exceeded a secondary rate limit. Please wait a few minutes.']),
        );

        Expect::exception(RateLimitException::class)->withMessageContaining('secondary rate limit exceeded');

        $validator->validate(self::releasesRequest(), $response);
    }

    #[Test]
    public function forbiddenResponseMentionsRepositoryAndToken(): void
    {
        $validator = new ResponseValidator(authenticated: true);
        $response = new ResponseStub(403, [], \json_encode(['message' => 'Resource not accessible by integration']));

        try {
            $validator->validate(self::releasesRequest(), $response);
            Assert::fail('AccessDeniedException is expected.');
        } catch (AccessDeniedException $e) {
            Assert::same($e->repository, 'owner/repo');
            Assert::string($e->getMessage())->contains('Resource not accessible by integration');
            Assert::string($e->getMessage())->contains('no read access to this repository');
        }
    }

    #[Test]
    public function notFoundResponseSuggestsCheckingRepositoryAddress(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(404, [], \json_encode(['message' => 'Not Found']));

        try {
            $validator->validate(self::releasesRequest(), $response);
            Assert::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            Assert::string($e->getMessage())->contains('repository `owner/repo`');
            Assert::string($e->getMessage())->contains('GITHUB_TOKEN');
        }
    }

    #[Test]
    public function serverErrorIsReportedAsTemporaryFailure(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $response = new ResponseStub(503, [], 'Service Unavailable', 'Service Unavailable');

        Expect::exception(ApiException::class)->withMessageContaining('GitHub API is unavailable: HTTP 503');

        $validator->validate(self::releasesRequest(), $response);
    }

    #[Test]
    public function missingAssetIsNotReportedAsMissingRepository(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://github.com/owner/repo/releases/download/v1.0.0/asset.zip');
        $response = new ResponseStub(404, [], 'Not Found');

        try {
            $validator->validate($request, $response);
            Assert::fail('AssetNotFoundException is expected.');
        } catch (AssetNotFoundException $e) {
            // The repository is still known, but the advice about tokens and addresses would mislead
            Assert::same($e->repository, 'owner/repo');
            Assert::string($e->getMessage())->contains('asset is no longer available');
            Assert::string($e->getMessage())->contains('release may have been deleted');
            Assert::string($e->getMessage())->notContains('GITHUB_TOKEN');
        }
    }

    #[Test]
    public function forbiddenAssetIsStillAnAccessProblem(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://github.com/owner/repo/releases/download/v1.0.0/asset.zip');

        Expect::exception(AccessDeniedException::class);

        $validator->validate($request, new ResponseStub(403, [], 'Forbidden'));
    }

    #[Test]
    public function transportFailureKeepsTheOriginalError(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $original = new \RuntimeException('Could not resolve host: api.github.com');

        $exception = $validator->transportFailure(self::releasesRequest(), $original);

        Assert::string($exception->getMessage())->contains('Failed to reach GitHub API');
        Assert::string($exception->getMessage())->contains('Could not resolve host');
        Assert::same($exception->getPrevious(), $original);
        Assert::same($exception->repository, 'owner/repo');
    }

    #[Test]
    public function longApiMessageIsTruncatedWithoutBreakingUtf8(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        // An ASCII prefix shifts the byte-based cut into the middle of a multibyte character
        $apiMessage = 'x' . \str_repeat('я', 400);
        $response = new ResponseStub(422, [], \json_encode(['message' => $apiMessage]));

        try {
            $validator->validate(self::releasesRequest(), $response);
            Assert::fail('ApiException is expected.');
        } catch (ApiException $e) {
            $message = $e->getMessage();
            Assert::same(\preg_match('//u', $message), 1, 'The message must stay valid UTF-8.');
            Assert::string($message)->contains('…');
        }
    }

    private static function releasesRequest(): RequestInterface
    {
        return new Request('GET', 'https://api.github.com/repos/owner/repo/releases?page=1');
    }
}
