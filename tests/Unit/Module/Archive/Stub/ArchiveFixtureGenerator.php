<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Stub;

/**
 * Helper class to generate archive files for tests
 */
final class ArchiveFixtureGenerator
{
    private string $fixturesDir;

    /**
     * @param string $fixturesDir Directory where archive fixtures will be stored
     */
    public function __construct(string $fixturesDir)
    {
        $this->fixturesDir = $fixturesDir;

        if (!\is_dir($fixturesDir)) {
            \mkdir($fixturesDir, 0777, true);
        }
    }

    /**
     * Create test archive files of different formats
     *
     * Creates a fixture file for each supported archive format
     *
     * @return array<string, string> Map of archive type to file path
     */
    public function generateArchives(): array
    {
        $fixtures = [];

        // Create a simple text file for content
        $contentFile = $this->fixturesDir . '/test-content.txt';
        \file_put_contents($contentFile, 'This is test content for archive');

        // Create ZIP archive
        $zipPath = $this->fixturesDir . '/archive.zip';
        if ($this->canCreateZip()) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFile($contentFile, 'test-content.txt');
                $zip->close();
                $fixtures['zip'] = $zipPath;
            }
        }

        // Create TAR.GZ archive
        $tarGzPath = $this->fixturesDir . '/archive.tar.gz';
        if ($this->canCreatePhar()) {
            try {
                $phar = new \PharData($this->fixturesDir . '/archive.tar');
                $phar->addFile($contentFile, 'test-content.txt');
                $phar->compress(\Phar::GZ);
                $fixtures['tar.gz'] = $tarGzPath;
            } catch (\Exception $e) {
                // Skip if creating tar.gz fails
            }
        }

        // Create PHAR archive
        $pharPath = $this->fixturesDir . '/archive.phar';
        if ($this->canCreatePhar() && \ini_get('phar.readonly') == 0) {
            try {
                $phar = new \Phar($pharPath);
                $phar->addFile($contentFile, 'test-content.txt');
                $fixtures['phar'] = $pharPath;
            } catch (\Exception $e) {
                // Skip if creating phar fails
            }
        }

        return $fixtures;
    }

    /**
     * Clean up generated fixtures
     */
    public function cleanup(): void
    {
        $this->removeDirectory($this->fixturesDir);
    }

    /**
     * Check if ZIP archive creation is possible
     */
    private function canCreateZip(): bool
    {
        return \class_exists(\ZipArchive::class);
    }

    /**
     * Check if PHAR archive creation is possible
     */
    private function canCreatePhar(): bool
    {
        return \class_exists(\PharData::class);
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
