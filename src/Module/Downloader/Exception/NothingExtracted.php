<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Exception;

/**
 * Downloaded asset contains no file that matches the extraction rules.
 *
 * Such a case used to be silent: the asset was downloaded and thrown away, and nothing was installed.
 *
 * @internal
 */
final class NothingExtracted extends \RuntimeException
{
    /** Maximum number of archive entries listed in the message. */
    private const FILES_LIMIT = 20;

    /**
     * @param string $assetName Name of the downloaded asset
     * @param list<string> $rules Patterns that were applied to the archive entries
     * @param list<string> $files Names of the files found in the archive
     */
    public function __construct(
        string $assetName,
        array $rules,
        array $files,
    ) {
        $listed = \array_slice($files, 0, self::FILES_LIMIT);
        $hidden = \count($files) - \count($listed);

        parent::__construct(
            \sprintf(
                "Nothing was extracted from `%s`: none of the %d file(s) inside matches the extraction rules.\n"
                . "Extraction rules: %s\n"
                . 'Files in the asset: %s%s',
                $assetName,
                \count($files),
                $rules === [] ? 'none' : \implode(', ', $rules),
                $listed === [] ? 'none' : \implode(', ', $listed),
                $hidden > 0 ? \sprintf(' and %d more', $hidden) : '',
            ),
        );
    }
}
