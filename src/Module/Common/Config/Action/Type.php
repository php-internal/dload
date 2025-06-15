<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Action;

/**
 * Download action type enumeration.
 *
 * Defines the processing behavior for downloaded assets based on their intended use.
 * Each type determines how the asset is filtered, downloaded, and post-processed.
 */
enum Type: string
{
    /**
     * Binary executable type.
     *
     * Downloads executable binaries that may be extracted from archives.
     * Performs version checking, sets executable permissions, and handles
     * binary extraction from compressed archives when needed.
     */
    case Binary = 'binary';

    /**
     * Archive extraction type.
     *
     * Downloads and extracts entire archive contents to specified directory.
     * Used for distributing multiple files, documentation, or project assets.
     */
    case Archive = 'archive';

    /**
     * PHP Archive (PHAR) type.
     *
     * Downloads PHAR files as self-contained PHP executables.
     * Treats PHAR as a binary but with PHP-specific filtering and no extraction.
     */
    case Phar = 'phar';
}
