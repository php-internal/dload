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
            // Derive output filename by stripping .gz extension
            $fileName = $this->asset->getFilename();
            $outputName = \preg_replace('/\.gz$/i', '', $fileName) ?? $fileName;
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

            /** @var \SplFileInfo|null $fileTo */
            $fileTo = yield $tempPath => $fileInfo;

            if ($fileTo instanceof \SplFileInfo) {
                \copy($tempPath, $fileTo->getRealPath() ?: $fileTo->getPathname());
            }
        } finally {
            \gzclose($gz);
        }
    }
}
