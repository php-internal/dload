<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(TarPharArchive::class)]
final class TarPharArchiveTest
{
    /** A tar.gz with `pkg-1.0/bin/app` (`binary\n`), `pkg-1.0/lib/app/libphp.so` and `pkg-1.0/share/app/VERSION.txt`. */
    private const NESTED_TAR_GZ = __DIR__ . '/../../../../Integration/Module/Archive/Fixture/nested.tar.gz';

    private string $workDir;

    public static function provideArchiveNames(): \Generator
    {
        yield 'tar.gz' => ['tool-linux-amd64.tar.gz'];
        yield 'tgz' => ['tool-linux-amd64.tgz'];
        yield 'uppercase tgz' => ['tool-linux-amd64.TGZ'];
        yield 'tar' => ['tool-linux-amd64.tar'];
    }

    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(false);
        $file->allows('getFilename')->andReturn('unreadable.tar.gz');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.tar.gz" is not readable.');

        new TarPharArchive($file);
    }

    #[DataProvider('provideArchiveNames')]
    #[Test]
    public function entriesListsTheArchiveRelativePaths(string $archiveName): void
    {
        $entries = $this->tarArchive($archiveName)->entries();
        \sort($entries);

        Assert::same($entries, ['pkg-1.0/bin/app', 'pkg-1.0/lib/app/libphp.so', 'pkg-1.0/share/app/VERSION.txt']);
    }

    #[DataProvider('provideArchiveNames')]
    #[Test]
    public function extractCopiesTheBinaryToTheDestinationSentBack(string $archiveName): void
    {
        $destination = $this->workDir . \DIRECTORY_SEPARATOR . 'app';

        $extractor = $this->tarArchive($archiveName)->extract();
        while ($extractor->valid() && $extractor->key() !== 'pkg-1.0/bin/app') {
            $extractor->next();
        }
        $extractor->send(new \SplFileInfo($destination));

        Assert::same(\file_get_contents($destination), "binary\n");
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->workDir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-tar-' . \uniqid();
        \mkdir($this->workDir, 0777, true);
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        foreach (\array_diff((array) \scandir($this->workDir), ['.', '..']) as $entry) {
            \unlink($this->workDir . \DIRECTORY_SEPARATOR . $entry);
        }

        \rmdir($this->workDir);
    }

    /**
     * Writes the fixture under the given name, decompressed for a plain tar.
     *
     * The bytes are copied rather than built with PharData: a tarball created and reopened within
     * the same process reads its entries back as empty on Linux.
     */
    private function tarArchive(string $archiveName): TarPharArchive
    {
        $content = (string) \file_get_contents(self::NESTED_TAR_GZ);
        \str_ends_with(\strtolower($archiveName), '.tar') and $content = (string) \gzdecode($content);

        $archivePath = $this->workDir . \DIRECTORY_SEPARATOR . $archiveName;
        \file_put_contents($archivePath, $content);

        return new TarPharArchive(new \SplFileInfo($archivePath));
    }
}
