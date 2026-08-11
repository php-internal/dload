<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\HttpClient;

use Internal\DLoad\Module\HttpClient\StreamReader;
use Internal\DLoad\Tests\Unit\Module\HttpClient\Stub\StreamStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(StreamReader::class)]
final class StreamReaderTest
{
    #[Test]
    public function chunksAreYieldedInOrder(): void
    {
        $stream = new StreamStub(['first', 'second', 'third']);

        $chunks = \iterator_to_array(StreamReader::chunks($stream));

        Assert::same($chunks, ['first', 'second', 'third']);
    }

    #[Test]
    public function drainedStreamYieldsNothing(): void
    {
        $stream = new StreamStub();

        $chunks = \iterator_to_array(StreamReader::chunks($stream));

        Assert::same($chunks, []);
    }

    #[Test]
    public function progressReportsTheCumulativeSizeAgainstTheTotal(): void
    {
        $stream = new StreamStub(['abc', 'de'], size: 5);
        $reports = [];

        $chunks = \iterator_to_array(StreamReader::chunks(
            $stream,
            static function (int $loaded, ?int $size) use (&$reports): void {
                $reports[] = [$loaded, $size];
            },
        ));

        Assert::same($chunks, ['abc', 'de']);
        Assert::same($reports, [[3, 5], [5, 5]]);
    }

    /**
     * An empty read means the stream has nothing left, whatever `eof()` claims. Reading past it
     * would both break the non-empty-chunk contract and, for a stream whose `eof()` never flips,
     * loop forever.
     */
    #[Test]
    public function readingStopsAtTheFirstEmptyChunk(): void
    {
        $stream = new StreamStub(['head', '', 'unreachable']);

        $chunks = \iterator_to_array(StreamReader::chunks($stream));

        Assert::same($chunks, ['head']);
    }

    #[Test]
    public function streamThatNeverReportsEofStillTerminates(): void
    {
        $stream = new StreamStub(['only'], eofWhenDrained: false);

        $chunks = [];
        foreach (StreamReader::chunks($stream) as $chunk) {
            $chunks[] = $chunk;

            # Bail out rather than hang if the generator ever stops terminating on its own.
            if (\count($chunks) > 2) {
                break;
            }
        }

        Assert::same($chunks, ['only']);
    }

    #[Test]
    public function chunkSizeIsPassedToTheStream(): void
    {
        $stream = new StreamStub(['payload']);

        \iterator_to_array(StreamReader::chunks($stream, chunkSize: 16));

        Assert::same($stream->readLengths, [16]);
    }
}
