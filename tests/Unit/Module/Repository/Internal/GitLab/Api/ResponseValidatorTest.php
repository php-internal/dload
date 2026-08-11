<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\ResponseValidator;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseValidator::class)]
final class ResponseValidatorTest extends TestCase
{
    public function testProjectPathIsDecodedFromApiUrl(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases?page=1');
        $response = new ResponseStub(404, [], \json_encode(['message' => '404 Project Not Found']));

        // Act
        try {
            $validator->validate($request, $response);
            self::fail('RepositoryNotFoundException is expected.');
        } catch (RepositoryNotFoundException $e) {
            // Assert
            self::assertSame('group/project', $e->repository);
            self::assertStringContainsString('project `group/project`', $e->getMessage());
            self::assertStringContainsString('GITLAB_TOKEN', $e->getMessage());
        }
    }

    public function testTooManyRequestsIsReportedAsRateLimit(): void
    {
        // Arrange
        $validator = new ResponseValidator(authenticated: false);
        $request = new Request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases');
        $response = new ResponseStub(429, ['retry-after' => ['30']], '');

        // Act
        try {
            $validator->validate($request, $response);
            self::fail('RateLimitException is expected.');
        } catch (RateLimitException $e) {
            // Assert
            self::assertStringContainsString('GitLab API rate limit exceeded', $e->getMessage());
            self::assertStringContainsString('GITLAB_TOKEN', $e->getMessage());
            self::assertNotNull($e->resetAt);
        }
    }
}
