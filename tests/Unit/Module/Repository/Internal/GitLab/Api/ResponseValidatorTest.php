<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\ResponseValidator;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Nyholm\Psr7\Request;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(ResponseValidator::class)]
final class ResponseValidatorTest
{
    #[Test]
    public function projectPathIsDecodedFromApiUrl(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases?page=1');
        $response = new ResponseStub(404, [], \json_encode(['message' => '404 Project Not Found']));

        try {
            $validator->validate($request, $response);
            Assert::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            Assert::same($e->repository, 'group/project');
            Assert::string($e->getMessage())->contains('project `group/project`');
            Assert::string($e->getMessage())->contains('GITLAB_TOKEN');
        }
    }

    #[Test]
    public function tooManyRequestsIsReportedAsRateLimit(): void
    {
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases');
        $response = new ResponseStub(429, ['retry-after' => ['30']], '');

        try {
            $validator->validate($request, $response);
            Assert::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            Assert::string($e->getMessage())->contains('GitLab API rate limit exceeded');
            Assert::string($e->getMessage())->contains('GITLAB_TOKEN');
            Assert::notNull($e->resetAt);
        }
    }
}
