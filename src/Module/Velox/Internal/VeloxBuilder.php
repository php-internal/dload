<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Config\Schema\Downloader;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Velox\Builder;
use Internal\DLoad\Module\Velox\Exception\Build as BuildException;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Result;
use Internal\DLoad\Module\Velox\Task;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Basic Velox builder implementation with local config support.
 *
 * Provides a synchronous implementation for building RoadRunner binaries
 * using Velox with local configuration files.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class VeloxBuilder implements Builder
{
    public function __construct(
        private readonly DependencyChecker $dependencyChecker,
        private readonly Logger $logger,
        private readonly Downloader $appConfig,
        private readonly OperatingSystem $operatingSystem,
        private readonly BinaryProvider $binaryProvider,
    ) {}

    public function build(VeloxAction $config, \Closure $onProgress): Task
    {
        $handler = function () use ($config, $onProgress): PromiseInterface {
            try {
                // $this->validate($config);

                # Prepare the destination binary path
                $destination = Path::create($config->binaryPath ?? 'rr')->absolute();
                $destination->extension() !== $this->operatingSystem->getBinaryExtension() and $destination = $destination
                    ->parent()
                    ->join($destination->stem() . $this->operatingSystem->getBinaryExtension());

                # Prepare environment
                # Create build directory
                $buildDir = FS::tmpDir($this->appConfig->tmpDir, 'velox-build');

                # Check required Dependencies
                $dependencyChecker = $this->dependencyChecker->withConfig($config, $buildDir);

                # 1. Golang globally
                $goBinary = $dependencyChecker->prepareGolang();

                # 2. Velox locally or globally (downloads if not found)
                $vxBinary = $dependencyChecker->prepareVelox();

                # Prepare configuration file
                $configPath = $this->prepareConfig($config, $buildDir);

                # Build
                # Execute build command
                $builtPath = $this->executeBuild($configPath, $buildDir, $vxBinary);
                # Move built binary to destination
                $binary = $this->installBinary($builtPath, $destination);

                return resolve(new Result(
                    binary: $binary,
                    metadata: [
                        'velox_version' => $vxBinary->getVersion(),
                        'golang_version' => $goBinary->getVersion(),
                        'build_config' => $config,
                    ],
                ));
            } catch (\Throwable $e) {
                return reject($e);
            } finally {
                # Remove the build directory
                isset($buildDir) and FS::remove($buildDir);
            }
        };

        return new Task($config, $onProgress, $handler, $this->getBuildName($config));
    }

    public function validate(VeloxAction $config): void
    {
        ConfigValidator::validate($config);

        // For this basic implementation, only local config files are supported
        if ($config->configFile === null) {
            throw new ConfigException(
                'This implementation only supports local config files. Remote API configuration is not yet implemented.',
            );
        }
    }

    private function prepareConfig(VeloxAction $config, Path $buildDir): Path
    {
        $sourceConfig = Path::create($config->configFile ?? 'velox.toml');
        $targetConfig = $buildDir->join('velox.toml');

        \copy($sourceConfig->__toString(), $targetConfig->__toString()) or throw new ConfigException(
            "Failed to copy config file from `{$sourceConfig}` to `{$targetConfig}`",
            configPath: $config->configFile,
        );

        $this->logger->debug('Copied config file to: %s', (string) $targetConfig);

        return $targetConfig;
    }

    /**
     * Executes the Velox build command with the provided configuration.
     *
     * @param Path $configPath Path to the Velox configuration file
     * @param Path $buildDir Directory where the built binary will be placed
     * @param Binary $vxBinary The Velox binary to use for building
     *
     * @return Path The path to the built binary
     *
     * @throws BuildException If the build fails or the binary is not found
     */
    private function executeBuild(Path $configPath, Path $buildDir, Binary $vxBinary): Path
    {
        $this->logger->info('Building...');
        $output = $vxBinary->execute(
            'build',
            # Specify the build directory
            '-o',
            $buildDir->absolute()->__toString(),
            # Specify the configuration file
            '-c',
            $configPath->absolute()->__toString(),
        );

        $this->logger->info('Build completed successfully.');

        // Look for the built binary
        return $this->findBuiltBinary($buildDir) ?? throw new BuildException(
            'Built binary not found in the build directory.',
            buildOutput: \implode("\n", $output),
        );
    }

    /**
     * Searches for the built binary in the specified build directory.
     *
     * @param Path $buildDir The directory where the binary is expected to be found
     *
     * @return Path|null The path to the built binary, or null if not found
     */
    private function findBuiltBinary(Path $buildDir): ?Path
    {
        // Common locations where velox places built binaries
        $searchPaths = [
            $buildDir->join('rr'),
            $buildDir->join('rr' . $this->operatingSystem->getBinaryExtension()),
            $buildDir->join('roadrunner'),
            $buildDir->join('roadrunner' . $this->operatingSystem->getBinaryExtension()),
        ];

        foreach ($searchPaths as $path) {
            if ($path->exists() && $path->isFile()) {
                $this->logger->debug('Found built binary: %s', (string) $path);
                return $path;
            }
        }

        return null;
    }

    /**
     * Installs the built binary to the specified destination.
     *
     * @param Path $builtBinary The path to the built binary
     * @param Path $destination The destination path where the binary should be installed
     *
     * @throws \RuntimeException If the destination cannot be created or the binary cannot be moved
     */
    private function installBinary(Path $builtBinary, Path $destination): Binary
    {
        # Check if build binary already exists
        $destination->exists()
            ? FS::remove($destination)
            : FS::mkdir($destination->parent());

        FS::moveFile($builtBinary, $destination);

        // Set executable permissions
        \chmod($destination->__toString(), 0755);

        $this->logger->info('Installed binary to: %s', $destination->__toString());

        $binaryConfig = new BinaryConfig();
        $binaryConfig->versionCommand = '--version';
        $binaryConfig->name = $destination->stem();
        return $this->binaryProvider->getLocalBinary($destination->parent(), $binaryConfig) ?? throw new \RuntimeException(
            "Failed to create binary instance for: {$destination}",
        );
    }

    /**
     * Gets a descriptive name for the build action.
     */
    private function getBuildName(VeloxAction $veloxAction): string
    {
        if ($veloxAction->configFile !== null) {
            return "Velox build (config: {$veloxAction->configFile})";
        }

        if ($veloxAction->plugins !== []) {
            $pluginNames = \array_map(static fn($plugin) => $plugin->name, $veloxAction->plugins);
            $pluginCount = \count($pluginNames);

            if ($pluginCount <= 3) {
                return 'Velox build (plugins: ' . \implode(', ', $pluginNames) . ')';
            }

            return "Velox build ({$pluginCount} plugins)";
        }

        return 'Velox build';
    }
}
