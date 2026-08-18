<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;

/**
 * Archive handler for standalone GZ (gzip) compressed files
 *
 * Uses PHP's zlib extension to decompress single gzip-compressed files.
 * The extracted file name is derived by stripping the `.gz` extension.
 *
 * @internal
 */
final class GzArchive extends Archive
{
    /**
     * @return \Generator<non-empty-string, \SplFileInfo, \SplFileInfo|null, void>
     */
    public function extract(): \Generator
    {
        $sourcePath = $this->asset->getRealPath() ?: $this->asset->getPathname();

        $gz = \gzopen($sourcePath, 'rb');
        $gz !== false or throw new ArchiveException(
            \sprintf('Could not open "%s" for reading.', $this->asset->getPathname()),
        );

        try {
            $outputName = $this->outputName();
            $tempPath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . $outputName;

            $out = \fopen($tempPath, 'wb');
            $out !== false or throw new ArchiveException(
                \sprintf('Could not create temporary file "%s".', $tempPath),
            );

            try {
                while (!\gzeof($gz)) {
                    $chunk = \gzread($gz, 8192);
                    if ($chunk === false) {
                        break;
                    }
                    \fwrite($out, $chunk);
                }
            } finally {
                \fclose($out);
            }

            $fileInfo = new \SplFileInfo($tempPath);

            // The archive-relative path of a single-file gzip is just the decompressed file name.
            /** @var \SplFileInfo|null $fileTo */
            $fileTo = yield $outputName => $fileInfo;

            if ($fileTo instanceof \SplFileInfo) {
                \copy($tempPath, $fileTo->getRealPath() ?: $fileTo->getPathname());
            }
        } finally {
            \gzclose($gz);
        }
    }

    public function entries(): array
    {
        // The single decompressed file name — derived without touching the gzip stream.
        return [$this->outputName()];
    }

    /**
     * Derives the decompressed file name by stripping the `.gz` extension.
     *
     * @return non-empty-string
     */
    private function outputName(): string
    {
        $fileName = $this->asset->getFilename();
        \assert($fileName !== '');
        $outputName = \preg_replace('/\.gz$/i', '', $fileName);

        return $outputName === null || $outputName === '' ? $fileName : $outputName;
    }
}
