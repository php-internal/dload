<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal;

use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Download;
use Internal\DLoad\Module\Config\Schema\Action\Type as DownloadType;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxConfig;
use Internal\DLoad\Module\Config\Schema\Actions;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Velox\Exception\Dependency as DependencyException;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Service\Logger;

use function React\Async\await;

/**
 * Dependency checker for Velox build requirements.
 *
 * Checks for the availability of required binaries like Go and Velox,
 * and validates their versions against configured constraints.
 *
 * @internal
 */
final class DependencyChecker
{
    private const VELOX_BINARY_NAME = 'vx';
    private const GOLANG_BINARY_NAME = 'go';

    private Path $veloxPath;
    private VeloxConfig $config;
    private Path $buildDirectory;

    public function __construct(
        private readonly BinaryProvider $binaryProvider,
        private readonly Logger $logger,
        private readonly SoftwareCollection $softwareCollection,
        private readonly Actions $actions,
        private readonly DLoad $downloader,
    ) {}

    /**
     * Checks if Go (golang) is available.
     *
     * @return Binary The Go binary instance
     *
     * @throws DependencyException When Go is not found
     */
    public function prepareGolang(): Binary
    {
        $this->checkServiceState();

        # Prepare config
        $binaryConfig = new BinaryConfig();
        $binaryConfig->name = self::GOLANG_BINARY_NAME;
        $binaryConfig->versionCommand = 'version';

        # Find Golang binary
        $binary = $this->binaryProvider->getGlobalBinary($binaryConfig, 'Go') ?? throw new DependencyException(
            'Go (golang) binary not found. Please install Go or ensure it is in your PATH.',
            dependencyName: self::GOLANG_BINARY_NAME,
        );

        # Check Go version
        if (!$this->checkBinaryVersion($binary, $this->config->golangVersion)) {
            throw new DependencyException(
                \sprintf(
                    'Go binary version `%s` does not satisfy the required constraint `%s`',
                    (string) $binary->getVersion(),
                    (string) $this->config->golangVersion,
                ),
                dependencyName: self::GOLANG_BINARY_NAME,
            );
        }

        $this->logger->debug('Found Go binary: %s', (string) $binary->getPath());

        return $binary;
    }

    /**
     * Checks if Velox is available.
     *
     * @throws DependencyException When Velox is not found
     */
    public function prepareVelox(): Binary
    {
        $this->checkServiceState();

        # Prepare config
        $softwareConfig = $this->softwareCollection->findSoftware('velox');
        $binaryConfig = $softwareConfig?->binary;
        if ($binaryConfig === null) {
            $binaryConfig = new BinaryConfig();
            $binaryConfig->name = 'vx';
            $binaryConfig->versionCommand = '--version';
        }

        # Check Velox globally
        try {
            $binary = $this->binaryProvider->getGlobalBinary($binaryConfig, 'Velox');
            if ($binary !== null) {
                $this->logger->debug(
                    'Found global Velox binary: %s (%s)',
                    (string) $binary->getPath()->absolute(),
                    (string) $binary->getVersion(),
                );
                if ($this->checkBinaryVersion($binary, $this->config->veloxVersion)) {
                    return $binary;
                }

                $this->logger->debug(
                    'Velox binary version `%s` does not satisfy the required constraint `%s`',
                    (string) $binary->getVersion(),
                    (string) $this->config->veloxVersion,
                );
            }
        } catch (\Throwable) {
            // Do nothing
        }

        # Check Velox locally
        # 1. Check local binary
        $binary = $this->binaryProvider->getLocalBinary($this->veloxPath, $binaryConfig, 'Velox');
        if ($binary !== null) {
            $this->logger->debug(
                'Found local Velox binary: %s (%s)',
                (string) $binary->getPath()->absolute(),
                (string) $binary->getVersion(),
            );
            if ($this->checkBinaryVersion($binary, $this->config->veloxVersion)) {
                return $binary;
            }

            $this->logger->debug(
                'Velox binary version `%s` does not satisfy the required constraint `%s`',
                (string) $binary->getVersion(),
                (string) $this->config->veloxVersion,
            );
        }

        # 2. Check download actions
        /** @var list<Software> $softwareList */
        $downloads = [];
        $binary = $this->checkDownloadedVelox($softwareConfig, $downloads);
        if ($binary !== null) {
            $this->logger->debug(
                'Found downloaded Velox binary: %s (%s)',
                (string) $binary->getPath()->absolute(),
                (string) $binary->getVersion(),
            );
            return $binary;
        }

        # 3. If no binaries are found, download Velox
        $softwareConfig === null and throw new DependencyException(
            'Velox software configuration not found. Please ensure Velox is defined in your configuration.',
            dependencyName: 'Velox',
        );

        # Add a download action if no actions are configured
        $fallbackAction = Download::fromSoftwareId('velox');
        $fallbackAction->version = $this->config->veloxVersion;
        $fallbackAction->extractPath = $this->buildDirectory->__toString();
        $fallbackAction->type = DownloadType::Binary;

        $this->logger->info('Downloading Velox binary...');
        $binary = $this->downloadVelox($softwareConfig, $downloads, $fallbackAction);
        if ($binary !== null) {
            $this->logger->debug(
                'Downloaded Velox binary: %s (%s)',
                (string) $binary->getPath()->absolute(),
                (string) $binary->getVersion(),
            );
            return $binary;
        }

        # Throw exception if Velox is not found
        throw new DependencyException(
            'Velox binary not found. Please install Velox or ensure it is in your PATH.',
            dependencyName: 'Velox',
        );
    }

    /**
     * Sets the Velox configuration for dependency checks.
     *
     * @param VeloxConfig $config The Velox configuration to use
     * @param Path $buildDirectory The directory where the build will take place
     *
     * @return self A new instance with the updated configuration
     */
    public function withConfig(VeloxConfig $config, Path $buildDirectory): self
    {
        $self = clone $this;
        $self->config = $config;
        $self->veloxPath = Path::create('.');
        $self->buildDirectory = $buildDirectory;
        return $self;
    }

    /**
     * Checks if the binary version satisfies the constraint.
     *
     * @param Binary $binary The Velox binary to check
     * @param string|null $constraint The version constraint to check against
     *
     * @return bool True if the version is satisfied, false otherwise
     */
    private function checkBinaryVersion(Binary $binary, ?string $constraint): bool
    {
        if ($constraint === null) {
            return true;
        }

        $version = $binary->getVersion();
        $constrain =  Constraint::fromConstraintString($constraint);

        return $version !== null && $constrain->isSatisfiedBy($version);
    }

    /**
     * Checks if the Velox service is properly configured.
     *
     * @throws \LogicException If the configuration is not set
     */
    private function checkServiceState(): void
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        isset($this->config) or throw new \LogicException(
            'Velox configuration is not set. Use `withConfig()` to set it before checking dependencies.',
        );
    }

    /**
     * Checks if Velox is downloaded and returns the binary if available.
     *
     * @param Software|null $softwareConfig The software configuration for Velox
     * @param list<Download> $downloads The list of Velox download actions
     *
     * @return Binary|null The Velox binary if found, null otherwise
     */
    private function checkDownloadedVelox(?Software $softwareConfig, array &$downloads): ?Binary
    {
        $binaryConfig = $softwareConfig?->binary;
        if ($softwareConfig === null || $binaryConfig === null) {
            return null;
        }

        # Find the relevant download action for Velox
        foreach ($this->actions->downloads as $download) {
            $software = $this->softwareCollection->findSoftware($download->software);
            if ($software !== $softwareConfig) {
                continue;
            }

            # Get binary
            $binary = $this->binaryProvider
                ->getLocalBinary(Path::create($download->extractPath ?? '.'), $binaryConfig, $softwareConfig->name);

            if ($binary === null) {
                $downloads[] = $download;
                continue;
            }

            if ($this->config->veloxVersion === null) {
                return $binary;
            }

            # Check version constraint
            $constraint = Constraint::fromConstraintString($this->config->veloxVersion);
            $binaryVersion = $binary->getVersion();

            if ($binaryVersion === null || !$constraint->isSatisfiedBy($binaryVersion)) {
                $this->logger->debug(
                    'Found downloaded Velox binary in `%s` but version `%s` does not satisfy constraint `%s`',
                    $binary->getPath()->absolute()->__toString(),
                    (string) $binaryVersion,
                    $constraint->__toString(),
                );

                # If local binary does not satisfy the download version constraint,
                # then we can redownload it
                if ($binaryVersion !== null && $download->version !== null) {
                    Constraint::fromConstraintString($download->version)
                        ->isSatisfiedBy($binaryVersion) or $downloads[] = $download;
                }

                continue;
            }

            return $binary;
        }

        return null;
    }

    /**
     * Downloads the Velox binary based on the provided download actions.
     * If no downloads are configured or the download fails on version constraint,
     * it uses a fallback action to download Velox.
     *
     * @param Software $software The software configuration for Velox
     * @param list<Download> $downloads The list of download actions for Velox
     * @param Download $fallbackAction The fallback download action if no downloads are configured
     *
     * @return Binary|null The downloaded Velox binary or null
     *
     * @throws DependencyException If the download fails or the binary is not found
     */
    private function downloadVelox(Software $software, array $downloads, Download $fallbackAction): ?Binary
    {
        $usedFallback = false;

        try_download:
        foreach ($downloads as $download) {
            try {
                $promise = $this->downloader->addTask($download, force: true);
                $this->downloader->run();

                /** @var DloadResult $result */
                $result = await($promise);

                # Check if the binary download was successful
                $binary = $result->binary;
                if ($binary === null) {
                    $this->logger->debug('Download for Velox binary failed: no binary found.');
                    continue;
                }

                $constraint = $this->config->veloxVersion === null
                    ? null
                    : Constraint::fromConstraintString($this->config->veloxVersion);
                $binaryVersion = $binary->getVersion();

                # Check if the downloaded binary satisfies the version constraint
                if ($constraint === null or $binaryVersion !== null && $constraint->isSatisfiedBy($binaryVersion)) {
                    return $binary;
                }

                $this->logger->debug(
                    'Downloaded Velox binary `%s` with version `%s` does not satisfy the constraint `%s`',
                    $binary->getPath()->__toString(),
                    (string) $binaryVersion,
                    (string) $this->config->veloxVersion,
                );
                continue;
            } catch (\Throwable) {
                continue;
            }
        }

        if ($usedFallback) {
            return null; // No valid binary found after trying all downloads
        }

        # If no downloads were successful, use the fallback action
        $usedFallback = true;
        $downloads = [$fallbackAction];
        goto try_download;
    }
}
