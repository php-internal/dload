<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\AuthenticationException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\ResponseValidator as GitHubValidator;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\ResponseValidator as GitLabValidator;
use Internal\DLoad\Module\Repository\Internal\ResponseValidator;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Nyholm\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * Exercises the shared base validator through the concrete GitHub and GitLab flavors.
 */
#[Covers(ResponseValidator::class)]
final class ResponseValidatorTest
{
    #[Test]
    public function unauthorizedResponseReportsRejectedCredentials(): void
    {
        $validator = new GitHubValidator(authenticated: true);
        $response = new ResponseStub(401, [], \json_encode(['message' => 'Bad credentials']), 'Unauthorized');

        try {
            $validator->validate(self::githubRequest(), $response);
            Assert::fail('AuthenticationException is expected.');
        } catch (AuthenticationException $e) {
            Assert::same($e->repository, 'owner/repo');
            Assert::string($e->getMessage())
                ->contains('rejected the credentials')
                ->contains('Bad credentials')
                ->contains('invalid, expired or revoked')
                ->contains('GITHUB_TOKEN');
        }
    }

    #[Test]
    public function unauthorizedAnonymousResponseSuggestsConfiguringAToken(): void
    {
        $validator = new GitHubValidator(authenticated: false);
        $response = new ResponseStub(401, [], \json_encode(['message' => 'Requires authentication']), 'Unauthorized');

        try {
            $validator->validate(self::githubRequest(), $response);
            Assert::fail('AuthenticationException is expected.');
        } catch (AuthenticationException $e) {
            Assert::same($e->repository, 'owner/repo');
            Assert::string($e->getMessage())
                ->contains('rejected the credentials')
                ->contains('Requires authentication')
                ->contains('No API token is configured')
                ->contains('the request was anonymous')
                ->contains('GITHUB_TOKEN')
                ->notContains('invalid, expired or revoked');
        }
    }

    #[Test]
    public function notFoundWithTokenExplainsPrivateRepositoryAccess(): void
    {
        $validator = new GitHubValidator(authenticated: true);
        $response = new ResponseStub(404, [], \json_encode(['message' => 'Not Found']));

        try {
            $validator->validate(self::githubRequest(), $response);
            Assert::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            Assert::string($e->getMessage())
                ->contains('repository `owner/repo`')
                ->contains('a 404 is also returned instead of 403')
                ->contains('GITHUB_TOKEN');
        }
    }

    #[Test]
    public function anonymousRateLimitOmitsLimitCountWhenNoneIsKnown(): void
    {
        // GitLab exposes no numeric rate limit, so neither a header nor a fallback count is available
        $validator = new GitLabValidator(authenticated: false);
        $request = new Request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases');
        $response = new ResponseStub(429, [], '');

        try {
            $validator->validate($request, $response);
            Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            Assert::string($e->getMessage())
                ->contains('GitLab API rate limit exceeded')
                ->contains('No API token is configured')
                ->contains('GITLAB_TOKEN')
                ->notContains('requests per hour');
        }
    }

    #[Test]
    #[DataSet([30, 'sec'], 'reset within a minute is counted in seconds')]
    #[DataSet([-10, 'a moment'], 'a past reset time reads as a moment')]
    public function rateLimitResetCountdownIsHumanized(int $offsetSeconds, string $expected): void
    {
        $validator = new GitHubValidator(authenticated: true);
        $response = new ResponseStub(
            403,
            [
                'x-ratelimit-remaining' => ['0'],
                'x-ratelimit-reset' => [(string) (\time() + $offsetSeconds)],
            ],
            \json_encode(['message' => 'API rate limit exceeded']),
        );

        try {
            $validator->validate(self::githubRequest(), $response);
            Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            Assert::string($e->getMessage())
                ->contains('The limit resets at')
                ->contains($expected)
                ->notContains(' min ');
        }
    }

    #[Test]
    #[DataSet(['', false, ''], 'empty body carries no API message')]
    #[DataSet(['123', false, ''], 'a JSON scalar is not treated as a message')]
    #[DataSet(
        ['{"error":["Access forbidden.","Come back later."]}', true, 'Access forbidden. Come back later.'],
        'a list of error strings is joined',
    )]
    #[DataSet(
        ['["Legacy rate limit message.","https://docs"]', true, 'Legacy rate limit message.'],
        'the first item of a plain list is used',
    )]
    public function apiMessageIsExtractedFromBodyShapes(string $body, bool $hasMessage, string $expected): void
    {
        $validator = new GitHubValidator(authenticated: false);
        $response = new ResponseStub(503, [], $body, 'Service Unavailable');

        try {
            $validator->validate(self::githubRequest(), $response);
            Assert::fail('ApiException is expected.');
        } catch (ApiException $e) {
            $message = $e->getMessage();

            if ($hasMessage) {
                Assert::string($message)->contains($expected);
                return;
            }

            // A null message leaves nothing between the endpoint and the "Try again later." suffix
            Assert::string($message)->contains('). Try again later.');
            $body === '' or Assert::string($message)->notContains($body);
        }
    }

    private static function githubRequest(): RequestInterface
    {
        return new Request('GET', 'https://api.github.com/repos/owner/repo/releases?page=1');
    }
}
