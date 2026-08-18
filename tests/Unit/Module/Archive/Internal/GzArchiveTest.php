<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\GzArchive;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(GzArchive::class)]
final class GzArchiveTest
{
    private string $workDir;

    /** @var list<string> Paths to remove once the test is done. */
    private array $garbage = [];

    public static function provideArchiveNames(): \Generator
    {
        yield 'lowercase extension' => ['payload.txt.gz', 'payload.txt'];
        yield 'uppercase extension' => ['payload.txt.GZ', 'payload.txt'];
        yield 'no extension to strip' => ['payload', 'payload'];
        yield 'gz inside the name' => ['payload.gz.bin.gz', 'payload.gz.bin'];
    }

    #[Test]
    public function extractYieldsTheDecompressedFile(): void
    {
        $archive = $this->gzArchive('payload.txt.gz', 'compressed content');

        $generator = $archive->extract();
        $extracted = $generator->current();

        Assert::same(\file_get_contents($extracted->getPathname()), 'compressed content');
    }

    #[Test]
    public function extractKeysTheFileByItsArchiveRelativeName(): void
    {
        $archive = $this->gzArchive('payload.txt.gz', 'compressed content');

        $generator = $archive->extract();

        Assert::same($generator->key(), $generator->current()->getFilename());
        Assert::same($generator->key(), 'payload.txt');
    }

    #[Test]
    public function entriesReturnsTheDecompressedNameWithoutTouchingTheStream(): void
    {
        $archive = $this->gzArchive('payload.txt.gz', 'compressed content');

        Assert::same($archive->entries(), ['payload.txt']);
    }

    #[DataProvider('provideArchiveNames')]
    #[Test]
    public function extractStripsTheGzExtensionFromTheOutputName(string $archiveName, string $expectedName): void
    {
        $archive = $this->gzArchive($archiveName, 'compressed content');

        $generator = $archive->extract();

        Assert::same($generator->current()->getFilename(), $expectedName);
    }

    #[Test]
    public function extractCopiesTheFileToTheDestinationSentBack(): void
    {
        $archive = $this->gzArchive('payload.txt.gz', 'compressed content');
        $destination = $this->workDir . \DIRECTORY_SEPARATOR . 'unpacked.txt';

        $generator = $archive->extract();
        $generator->current();
        $generator->send(new \SplFileInfo($destination));

        Assert::true(\is_file($destination));
        Assert::same(\file_get_contents($destination), 'compressed content');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->workDir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-gz-' . \uniqid();
        \mkdir($this->workDir, 0777, true);
        $this->garbage[] = $this->workDir;
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        foreach ($this->garbage as $path) {
            \is_dir($path) ? self::removeDirectory($path) : (\is_file($path) and \unlink($path));
        }

        $this->garbage = [];
    }

    private static function removeDirectory(string $directory): void
    {
        foreach (\array_diff((array) \scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . \DIRECTORY_SEPARATOR . $entry;
            \is_dir($path) ? self::removeDirectory($path) : \unlink($path);
        }

        \rmdir($directory);
    }

    /**
     * Writes a real gzip archive and wraps it, registering the file the extraction will leave in
     * the system temp directory for cleanup.
     */
    private function gzArchive(string $archiveName, string $content): GzArchive
    {
        $archivePath = $this->workDir . \DIRECTORY_SEPARATOR . $archiveName;
        \file_put_contents($archivePath, (string) \gzencode($content));

        $outputName = \preg_replace('/\.gz$/i', '', $archiveName);
        $this->garbage[] = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . $outputName;

        return new GzArchive(new \SplFileInfo($archivePath));
    }
}
