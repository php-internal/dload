<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Action;

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
     * Downloads and extracts the entire archive into the destination directory, preserving the
     * internal directory structure. A single top-level directory wrapping the whole archive is
     * stripped (like `tar --strip-components=1`).
     *
     * Unlike {@see self::Binary}, matched files are not flattened — this suits multi-file tools
     * whose files reference each other by relative path (e.g. a binary resolving a shared library
     * via an `$ORIGIN/../lib` rpath). When `<file>` rules are given they act as an include filter;
     * a configured `<binary>` is only used to locate the executable for version checks, not moved.
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
