<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\HttpClient\Stub;

use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 stream that hands out a scripted sequence of chunks.
 *
 * Each `read()` returns the next scripted chunk, so a script may contain an empty string to
 * reproduce a stream that has nothing to give while still claiming not to be at its end.
 */
final class StreamStub implements StreamInterface
{
    /** @var list<int> Lengths every `read()` was called with, in order. */
    public array $readLengths = [];

    /** @var list<string> */
    private array $chunks;

    /**
     * @param list<string> $chunks Chunks to hand out, in order.
     * @param bool $eofWhenDrained Whether `eof()` flips to true once the script is exhausted.
     *        `false` reproduces a stream that never admits to being finished.
     * @param int|null $size Value reported by `getSize()`.
     */
    public function __construct(
        array $chunks = [],
        private readonly bool $eofWhenDrained = true,
        private readonly ?int $size = null,
    ) {
        $this->chunks = $chunks;
    }

    public function read(int $length): string
    {
        $this->readLengths[] = $length;

        return \array_shift($this->chunks) ?? '';
    }

    public function eof(): bool
    {
        return $this->eofWhenDrained && $this->chunks === [];
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getContents(): string
    {
        $contents = $this->__toString();
        $this->chunks = [];

        return $contents;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function tell(): int
    {
        return 0;
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

    public function getMetadata(?string $key = null)
    {
        return null;
    }

    public function __toString(): string
    {
        return \implode('', $this->chunks);
    }
}
