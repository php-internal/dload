<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\HttpClient;

use Psr\Http\Message\StreamInterface;

/**
 * Reads a PSR-7 stream as a sequence of non-empty chunks.
 *
 * @internal
 */
final class StreamReader
{
    /**
     * Yields the stream content chunk by chunk, reporting progress after each one.
     *
     * A stream may report `eof()` as false and still read nothing. That ends the iteration instead
     * of spinning forever, and is what keeps every yielded chunk non-empty.
     *
     * @param null|\Closure(int $loaded, int|null $size, array $info): mixed $progress
     *        Throwing from the closure aborts the read.
     * @param positive-int $chunkSize
     * @return \Generator<int, non-empty-string, mixed, void>
     */
    public static function chunks(
        StreamInterface $stream,
        ?\Closure $progress = null,
        int $chunkSize = 8192,
    ): \Generator {
        $size = $stream->getSize();
        $loaded = 0;

        while (!$stream->eof()) {
            $chunk = $stream->read($chunkSize);
            if ($chunk === '') {
                break;
            }

            $loaded += \strlen($chunk);
            $progress === null or $progress($loaded, $size, []);

            yield $chunk;
        }
    }
}
