<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Exception;

/**
 * Every matching asset of a release answered "not found": the release was deleted or its assets
 * were replaced after the release list was obtained.
 *
 * Unlike a plain `NotFound`, this says the release list itself is outdated.
 */
final class ReleaseGone extends \RuntimeException {}
