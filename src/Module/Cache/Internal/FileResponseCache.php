<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Cache\Internal;

use Internal\DLoad\Module\Cache\ResponseCache;
use Internal\DLoad\Service\Logger;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class FileResponseCache implements ResponseCache
{
    /**
     * @param non-empty-string $directory
     * @param int<1, max> $ttl
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $ttl,
        private readonly Logger $logger,
    ) {}

    public function remember(string $key, \Closure $fetch): ResponseInterface
    {
        $file = $this->fileOf($key);

        $cached = $this->read($file);
        if ($cached !== null) {
            return $cached;
        }

        $response = $fetch();
        $this->write($file, $response);

        return $response;
    }

    private static function readBody(ResponseInterface $response): string
    {
        $stream = $response->getBody();

        $stream->isSeekable() and $stream->rewind();
        $body = $stream->getContents();
        $stream->isSeekable() and $stream->rewind();

        return $body;
    }

    private function read(string $file): ?ResponseInterface
    {
        if (!\is_file($file)) {
            return null;
        }

        $content = @\file_get_contents($file);
        if ($content === false) {
            $this->discard($file);
            return null;
        }

        try {
            /** @var mixed $payload */
            $payload = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            \is_array($payload)
            && \is_int($payload['created_at'] ?? null)
            && \is_int($payload['status'] ?? null)
            && \is_array($payload['headers'] ?? null)
            && \is_string($payload['body'] ?? null)
                or throw new \UnexpectedValueException('Unexpected cache entry structure.');
        } catch (\Throwable) {
            $this->discard($file);
            return null;
        }

        /** @var array{created_at: int, status: int, headers: array<string, list<string>>, body: string} $payload */

        if (\time() - $payload['created_at'] > $this->ttl) {
            return null;
        }

        return new Response($payload['status'], $payload['headers'], $payload['body']);
    }

    private function write(string $file, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status > 299) {
            return;
        }

        try {
            $payload = \json_encode([
                'created_at' => \time(),
                'status' => $status,
                'headers' => $response->getHeaders(),
                'body' => self::readBody($response),
            ], JSON_THROW_ON_ERROR);

            if (!\is_dir($this->directory) && !@\mkdir($this->directory, 0777, true) && !\is_dir($this->directory)) {
                throw new \RuntimeException(\sprintf('Failed to create cache directory `%s`.', $this->directory));
            }

            $temp = $file . '.' . (string) \getmypid() . '.tmp';

            if (@\file_put_contents($temp, $payload) === false) {
                throw new \RuntimeException(\sprintf('Failed to write cache entry `%s`.', $temp));
            }

            if (!@\rename($temp, $file)) {
                @\unlink($temp);
                throw new \RuntimeException(\sprintf('Failed to store cache entry `%s`.', $file));
            }
        } catch (\Throwable $e) {
            $this->logger->exception($e, important: false);
        }
    }

    private function discard(string $file): void
    {
        @\unlink($file);
    }

    private function fileOf(string $key): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . \hash('xxh128', $key) . '.json';
    }
}
