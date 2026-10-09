<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Internal\DLoad\Module\Installer\Installation;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts the entries matched by the `<binary>` and `<file>` rules flat into the destination.
 *
 * Ends the installation pipeline: it gets every download no {@see InstallStep} has taken.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class RuleExtraction
{
    public function __construct(
        private readonly Logger $logger,
        private readonly OutputInterface $output,
        private readonly ArchiveFactory $archiveFactory,
        private readonly BinaryProvider $binaryProvider,
    ) {}

    /**
     * @throws NothingExtracted When no archive entry matched the rules
     */
    public function __invoke(Installation $installation): DloadResult
    {
        $software = $installation->software;
        $destination = $installation->destination;
        $fileInfo = $installation->download->file;
        $resultFiles = [];
        $resultBinary = null;

        $archive = $this->archiveFactory->create($fileInfo);
        $extractor = $archive->extract();
        $this->logger->info('Extracting %s', $fileInfo->getFilename());
        $binaryPattern = $installation->binaryRule;
        $archiveFiles = [];

        while ($extractor->valid()) {
            $to = $rule = null;
            $file = $extractor->current();
            \assert($file instanceof \SplFileInfo);
            $archiveFiles[] = $file->getFilename();

            # Check if it's binary and should be extracted
            $isBinary = false;
            if ($binaryPattern !== null) {
                [$to, $rule] = self::shouldBeExtracted($file, [$binaryPattern], $destination);
                $isBinary = $to !== null;
            }

            isset($to) or [$to, $rule] = self::shouldBeExtracted($file, $software->files, $destination);
            if ($to === null) {
                $this->logger->debug('Skipping file `%s`.', $file->getFilename());
                $extractor->next();
                continue;
            }

            $this->logger->debug('Extracting %s to %s...', $file->getFilename(), $to->getPathname());

            $isOverwriting = $to->isFile();
            $extractor->send($to);

            // Success
            $path = $to->getRealPath() ?: $to->getPathname();
            $this->output->writeln(
                \sprintf(
                    '%s (<comment>%s</comment>) has been %sinstalled into <info>%s</info>',
                    $to->getFilename(),
                    $installation->download->version,
                    $isOverwriting ? 're' : '',
                    $path,
                ),
            );

            \assert(isset($rule));
            $rule->chmod === null or @\chmod($path, $rule->chmod);

            # Add files and binary to result
            $path = Path::create($path);
            $resultFiles[] = $path;
            if ($isBinary && $software->binary !== null) {
                $resultBinary = $this->binaryProvider->getLocalBinary($path->parent(), $software->binary);
                $binaryPattern = null;
            }
        }

        # A downloaded asset without a single matching file means nothing was installed
        $resultFiles === [] and throw new NothingExtracted(
            assetName: $fileInfo->getFilename(),
            rules: $installation->describeRules(),
            files: $archiveFiles,
        );

        return new DloadResult($resultFiles, $resultBinary);
    }

    /**
     * Determines the target path for an extracted file based on file mapping configurations.
     *
     * @param \SplFileInfo $source Source file from the archive
     * @param list<File> $mapping File mapping configurations
     * @param Path $path Destination path where files should be extracted
     * @return array{\SplFileInfo, File}|array{null, null} Array containing:
     *         - Target file path or null if file should not be extracted
     *         - File configuration that matched the source file, or null if no match found
     */
    private static function shouldBeExtracted(\SplFileInfo $source, array $mapping, Path $path): array
    {
        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $extension = $source->getExtension();
                // Validate that the "extension" looks like a real file extension
                // (short, alphanumeric only — e.g. "exe", "phar", "gz")
                // and not a version/platform artifact like "0-linux-amd64"
                $hasRealExtension = $extension !== '' && \preg_match('/^(?=.*[a-zA-Z])[a-zA-Z0-9]{1,10}$/', $extension) === 1;

                $newName = match (true) {
                    $conf->rename === null => $source->getFilename(),
                    !$hasRealExtension => $conf->rename,
                    default => $conf->rename . '.' . $extension,
                };

                return [new \SplFileInfo((string) $path->join($newName)), $conf];
            }
        }

        return [null, null];
    }
}
