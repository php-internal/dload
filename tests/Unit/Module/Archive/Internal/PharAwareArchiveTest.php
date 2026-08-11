<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use Internal\DLoad\Module\Archive\Internal\PharAwareArchive;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(PharAwareArchive::class)]
final class PharAwareArchiveTest
{
    private \SplFileInfo $fileInfo;

    #[Test]
    public function extractThrowsExceptionWhenArchiveIsNotReadable(): void
    {
        $pharData = \Mockery::mock(\PharData::class);
        $pharData->allows('isReadable')->andReturn(false);
        $pharData->allows('getPathname')->andReturn('unreadable.phar');

        $archive = $this->createPharAwareArchive($pharData);

        Expect::exception(ArchiveException::class)->withMessage('Could not open "unreadable.phar" for reading.');

        \iterator_to_array($archive->extract());
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->fileInfo = \Mockery::mock(\SplFileInfo::class);
        $this->fileInfo->allows('isFile')->andReturn(true);
        $this->fileInfo->allows('isReadable')->andReturn(true);
    }

    /**
     * Creates a concrete implementation of the abstract PharAwareArchive for testing
     */
    private function createPharAwareArchive(\PharData $pharData): PharAwareArchive
    {
        return new class($this->fileInfo, $pharData) extends PharAwareArchive {
            private \PharData $testPharData;

            public function __construct(\SplFileInfo $asset, \PharData $pharData)
            {
                $this->testPharData = $pharData;
                parent::__construct($asset);
            }

            protected function open(\SplFileInfo $file): \PharData
            {
                return $this->testPharData;
            }
        };
    }
}
