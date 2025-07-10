<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Velox\Exception\Dependency as DependencyException;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Service\Logger;

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
        $binaryConfig = new BinaryConfig();
        $binaryConfig->name = self::VELOX_BINARY_NAME;
        $binaryConfig->versionCommand = '--version';

        # Check Velox globally
        $binary = $this->binaryProvider->getGlobalBinary($binaryConfig, 'Velox');
        if ($binary !== null && $this->checkVeloxVersion($binary)) {
            $this->logger->debug('Found global Velox binary: %s', (string) $binary->getPath());
            return $binary;
        }

        # Check Velox locally
        $binary = $this->binaryProvider->getLocalBinary(
            $this->veloxPath,
            $binaryConfig,
            'Velox',
        );
        if ($binary !== null && $this->checkVeloxVersion($binary)) {
            $this->logger->debug('Found local Velox binary: %s', (string) $binary->getPath());

            return $binary;
        }

        #   todo: download Velox if not installed (execute Download actions)
        #   todo: check download actions

        # Throw exception if Velox is not found
        $this->logger->error('Velox binary not found in PATH or local directory `%s`', (string) $this->veloxPath);
        throw new DependencyException(
            'Velox binary not found. Please install Velox or ensure it is in your PATH.',
            dependencyName: self::VELOX_BINARY_NAME,
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
     * Checks if the Velox version satisfies the configured constraint.
     *
     * @param Binary $binary The Velox binary to check
     *
     * @return bool True if the version is satisfied, false otherwise
     */
    private function checkVeloxVersion(Binary $binary): bool
    {
        if ($this->config->veloxVersion === null) {
            return true;
        }

        $version = $binary->getVersion();
        $constrain =  Constraint::fromConstraintString($this->config->veloxVersion);

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
}
