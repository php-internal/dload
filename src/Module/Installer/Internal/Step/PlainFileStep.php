<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal\Step;

use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Installer\Installation;
use Internal\DLoad\Module\Installer\Internal\InstallStep;
use Internal\DLoad\Service\Logger;

/**
 * Installs software without extraction rules: with neither `<binary>` nor `<file>` there is nothing
 * to look for inside the download, so it is moved into the destination as is.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class PlainFileStep implements InstallStep
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    public function install(Installation $installation, callable $next): DloadResult
    {
        if ($installation->software->files !== [] || $installation->software->binary !== null) {
            return $next($installation);
        }

        $this->logger->debug(
            'No files to extract for `%s`, copying the downloaded file to the destination.',
            $installation->download->file->getFilename(),
        );

        return new DloadResult([$installation->moveToDestination()]);
    }
}
