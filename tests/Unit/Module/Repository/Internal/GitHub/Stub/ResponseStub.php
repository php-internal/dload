<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * HTTP Response stub for GitHub API tests.
 *
 * Provides controllable response data for testing.
 */
final class ResponseStub implements ResponseInterface
{
    /**
     * @param array<string, string[]> $headers
     */
    public function __construct(
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
        private readonly string $body = '',
        private readonly string $reasonPhrase = 'OK',
        private readonly string $protocolVersion = '1.1',
    ) {}

    public static function ok(string $body = ''): self
    {
        return new self(200, [], $body);
    }

    public static function forbidden(string $body = ''): self
    {
        return new self(403, [], $body);
    }

    public static function githubRateLimit(): self
    {
        $rateLimitResponse = [
            'API rate limit exceeded for user ID 1234. Check the hourly limit for your plan at https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
            'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
        ];

        return new self(403, [], \json_encode($rateLimitResponse));
    }

    public static function githubForbidden(): self
    {
        return new self(403, [], \json_encode(['message' => 'Forbidden']));
    }

    public static function invalidJson(): self
    {
        return new self(403, [], 'invalid json response');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        return new self($code, $this->headers, $this->body, $reasonPhrase, $this->protocolVersion);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[\strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[\strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        $header = $this->getHeader($name);
        return \implode(', ', $header);
    }

    public function withHeader(string $name, $value): ResponseInterface
    {
        $headers = $this->headers;
        $headers[\strtolower($name)] = \is_array($value) ? $value : [$value];
        return new self($this->statusCode, $headers, $this->body, $this->reasonPhrase, $this->protocolVersion);
    }

    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $headers = $this->headers;
        $key = \strtolower($name);
        if (!isset($headers[$key])) {
            $headers[$key] = [];
        }
        $headers[$key] = \array_merge($headers[$key], \is_array($value) ? $value : [$value]);
        return new self($this->statusCode, $headers, $this->body, $this->reasonPhrase, $this->protocolVersion);
    }

    public function withoutHeader(string $name): ResponseInterface
    {
        $headers = $this->headers;
        unset($headers[\strtolower($name)]);
        return new self($this->statusCode, $headers, $this->body, $this->reasonPhrase, $this->protocolVersion);
    }

    public function getBody(): StreamInterface
    {
        return new class($this->body) implements StreamInterface {
            public function __construct(private readonly string $content) {}

            public function __toString(): string
            {
                return $this->content;
            }

            public function close(): void {}

            public function detach()
            {
                return null;
            }

            public function getSize(): ?int
            {
                return \strlen($this->content);
            }

            public function tell(): int
            {
                return 0;
            }

            public function eof(): bool
            {
                return true;
            }

            public function isSeekable(): bool
            {
                return false;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void {}

            public function rewind(): void {}

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                return 0;
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                return $this->content;
            }

            public function getContents(): string
            {
                return $this->content;
            }

            public function getMetadata(?string $key = null)
            {
                return null;
            }
        };
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        return new self($this->statusCode, $this->headers, (string) $body, $this->reasonPhrase, $this->protocolVersion);
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        return new self($this->statusCode, $this->headers, $this->body, $this->reasonPhrase, $version);
    }
}
