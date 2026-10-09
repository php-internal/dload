<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal\Step;

use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Installer\Installation;
use Internal\DLoad\Module\Installer\Internal\InstallStep;
use Internal\DLoad\Service\Logger;

/**
 * Installs a PHAR action: the download is moved into the destination as is and made executable.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class PharStep implements InstallStep
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    public function install(Installation $installation, callable $next): DloadResult
    {
        if ($installation->type !== Type::Phar) {
            return $next($installation);
        }

        $this->logger->debug(
            'Copying downloaded file `%s` to destination as a PHAR archive.',
            $installation->download->file->getFilename(),
        );
        $toFile = $installation->moveToDestination();
        \chmod((string) $toFile, 0o755);

        # todo: add PHAR binary to result
        return new DloadResult([$toFile]);
    }
}
