<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal\Step;

use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Installer\Installation;
use Internal\DLoad\Module\Installer\Internal\InstallStep;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Creates the destination directory before the installation and removes the temporary download after it,
 * whether the installation succeeded or not.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class WorkspaceStep implements InstallStep
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    public function install(Installation $installation, callable $next): DloadResult
    {
        $file = $installation->download->file;
        $tempFilePath = Path::create($file->getRealPath() ?: $file->getPathname());

        try {
            FS::mkdir($installation->destination);

            return $next($installation);
        } finally {
            if ($installation->temporary && $tempFilePath->exists()) {
                $this->logger->debug('Cleaning up temporary file: %s', $tempFilePath->__toString());
                FS::remove($tempFilePath);
            }
        }
    }
}
