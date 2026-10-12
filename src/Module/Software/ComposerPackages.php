<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

use Internal\Container\ObjectContainer;
use Internal\DLoad\Module\Common\Internal\Injection\ConfigInflector;
use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Config\Schema\Embed\Repository;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Software declared by the root package of the project and its direct Composer dependencies.
 *
 * A package opts in with `extra.dload.registry` in its `composer.json`: a path, inside the package,
 * to an XML file of the `dload.xml` format; only its `<registry>` section is read. Packages come from
 * `<vendor-dir>/composer/installed.json` (Composer 2 format), so no Composer plugin is involved.
 *
 * A broken declaration never fails the run: it is reported as a warning and skipped.
 *
 * ```php
 * $packages = new ComposerPackages(Path::create('.'), Path::create('dload.xml'), $logger);
 * foreach ($packages->entries() as $entry) {
 *     echo $entry->origin, ': ', $entry->getId(), "\n";
 * }
 * ```
 *
 * @internal
 */
final class ComposerPackages
{
    /** Name Composer gives a root package without one */
    private const ROOT_NAME = '__root__';

    /**
     * @param Path $projectDir Directory of the root `composer.json`.
     * @param Path|null $configFile The loaded `dload.xml`: its registry is already in the collection,
     *        so a package pointing to it is not read a second time.
     * @param string|null $vendorDir Overrides `config.vendor-dir`, as `COMPOSER_VENDOR_DIR` does for Composer.
     */
    public function __construct(
        private readonly Path $projectDir,
        private readonly ?Path $configFile,
        private readonly Logger $logger,
        private readonly ?string $vendorDir = null,
    ) {}

    /**
     * @return list<Entry> In the order of precedence between packages: the root package first,
     *         then the packages of the root `require`, then of `require-dev`, each in the order listed.
     */
    public function entries(): array
    {
        $root = $this->readJson($this->projectDir->join('composer.json'));
        if ($root === null) {
            return [];
        }

        $entries = $this->entriesOf(self::packageName($root) ?? self::ROOT_NAME, $root, $this->projectDir);

        $installed = $this->installedPackages($root);
        // A package listed in both `require` and `require-dev` keeps its first place.
        $names = \array_unique(\array_map(
            static fn(int|string $name): string => \strtolower((string) $name),
            [...\array_keys((array) ($root['require'] ?? [])), ...\array_keys((array) ($root['require-dev'] ?? []))],
        ));
        foreach ($names as $name) {
            $package = $installed[$name] ?? null;
            $package === null or \array_push($entries, ...$this->entriesOf($package[0], $package[1], $package[2]));
        }

        return $entries;
    }

    /**
     * @return non-empty-string|null What makes the definition unusable, or null when it is usable.
     */
    private static function problemOf(Software $software): ?string
    {
        $missing = self::missingAttribute($software);
        if ($missing !== null) {
            return \sprintf('`<software>` has no `%s`', $missing);
        }

        $id = $software->getId();
        if (\trim($id) === '') {
            return '`<software>` has an empty id';
        }

        if (\trim($id) !== $id || \strpbrk($id, '#:') !== false) {
            return \sprintf('the id `%s` cannot be requested: it has spaces around it, `#` or `:`', $id);
        }

        $children = [
            'binary' => $software->binary === null ? [] : [$software->binary],
            'repository' => $software->repositories,
            'file' => $software->files,
        ];
        foreach ($children as $element => $items) {
            foreach ($items as $item) {
                $missing = self::missingAttribute($item);
                if ($missing !== null) {
                    return \sprintf('a `<%s>` of `%s` has no `%s`', $element, $id, $missing);
                }
            }
        }

        $names = [
            $software->binary?->name,
            ...\array_map(static fn(File $file): ?string => $file->rename, $software->files),
        ];
        foreach ($names as $name) {
            if ($name !== null && !FS::isFileName($name)) {
                return \sprintf('`%s` of `%s` must be a file name, not a path', $name, $id);
            }
        }

        // Escaping keeps the shell out, not the binary: a package naming a binary the project already
        // has would choose its arguments, e.g. `bun vendor/evil/script.js` or `rr reset`.
        $versionCommand = $software->binary?->versionCommand;
        if ($versionCommand !== null && \preg_match('/^(--?[A-Za-z][A-Za-z-]*|version)$/', $versionCommand) !== 1) {
            return \sprintf('the version command `%s` of `%s` must be one option like `--version`, or `version`', $versionCommand, $id);
        }

        $patterns = [
            $software->binary?->pattern,
            ...\array_map(static fn(Repository $repository): string => $repository->assetPattern, $software->repositories),
            ...\array_map(static fn(File $file): string => $file->pattern, $software->files),
        ];
        foreach ($patterns as $pattern) {
            if ($pattern !== null && @\preg_match($pattern, '') === false) {
                return \sprintf('the pattern `%s` of `%s` is not a valid regular expression', $pattern, $id);
            }
        }

        return null;
    }

    /**
     * The hydrator leaves a required property uninitialized when its attribute is missing in the XML,
     * and sets it to an empty string when the attribute is empty.
     *
     * @return non-empty-string|null Name of the first required property without a value.
     */
    private static function missingAttribute(object $config): ?string
    {
        foreach ((new \ReflectionObject($config))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->hasDefaultValue()) {
                continue;
            }

            if (!$property->isInitialized($config)) {
                return $property->getName();
            }

            /** @var mixed $value */
            $value = $property->getValue($config);
            if (\is_string($value) && \trim($value) === '') {
                return $property->getName();
            }
        }

        return null;
    }

    /**
     * @return non-empty-string|null
     */
    private static function packageName(array $package): ?string
    {
        /** @var mixed $name */
        $name = $package['name'] ?? null;
        return \is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return array<string, array{non-empty-string, array, Path}> Name, `composer.json` data and
     *         install path of each installed package, by lowercased name.
     */
    private function installedPackages(array $root): array
    {
        /** @var mixed $vendorDir */
        $vendorDir = $this->vendorDir === null || $this->vendorDir === ''
            ? ((array) ($root['config'] ?? []))['vendor-dir'] ?? 'vendor'
            : $this->vendorDir;
        if (!\is_string($vendorDir) || $vendorDir === '' || \str_contains($vendorDir, "\0")) {
            $this->logger->warning('Skipping registries of Composer packages: `config.vendor-dir` is not a path.');
            return [];
        }

        $vendorPath = Path::create($vendorDir);
        $vendorPath->isAbsolute() or $vendorPath = $this->projectDir->join($vendorDir);

        $file = $vendorPath->join('composer', 'installed.json');
        if (!$file->exists()) {
            $this->logger->debug('Composer packages are not installed: `%s` not found.', (string) $file);
            return [];
        }

        $json = $this->readJson($file);
        if ($json === null) {
            return [];
        }

        if (!\is_array($json['packages'] ?? null)) {
            $this->logger->warning(
                'Skipping registries of Composer packages: `%s` is not in the Composer 2 format.',
                (string) $file,
            );
            return [];
        }

        $result = [];
        /** @var mixed $package */
        foreach ($json['packages'] as $package) {
            if (!\is_array($package)) {
                continue;
            }

            $name = self::packageName($package);
            $installPath = $package['install-path'] ?? null;
            if ($name === null || !\is_string($installPath) || $installPath === '' || \str_contains($installPath, "\0")) {
                continue;
            }

            // Composer writes the install path relative to the directory of installed.json.
            $path = Path::create($installPath);
            $path->isAbsolute() or $path = $file->parent()->join($installPath);
            $result[\strtolower($name)] = [$name, $package, $path];
        }

        return $result;
    }

    /**
     * @param non-empty-string $name
     * @param array $package Data of the package's `composer.json`.
     * @return list<Entry>
     */
    private function entriesOf(string $name, array $package, Path $installPath): array
    {
        /** @var mixed $dload */
        $dload = ((array) ($package['extra'] ?? []))['dload'] ?? null;
        if ($dload === null) {
            return [];
        }

        if (!\is_array($dload)) {
            $this->logger->warning(
                'Package `%s`: `extra.dload` must be an object, e.g. `{"registry": "path/to/dload.xml"}`.',
                $name,
            );
            return [];
        }

        /** @var mixed $registry */
        $registry = $dload['registry'] ?? null;
        if ($registry === null) {
            return [];
        }

        if (!\is_string($registry) || $registry === '' || \str_contains($registry, "\0")) {
            $this->logger->warning('Package `%s`: `extra.dload.registry` must be a path to a file.', $name);
            return [];
        }

        $file = $this->registryFile($name, $registry, $installPath);
        if ($file === null) {
            return [];
        }

        $origin = Origin::package($name);
        return \array_map(
            static fn(Software $software): Entry => new Entry($software, $origin),
            $this->readRegistry($name, $file),
        );
    }

    /**
     * Resolves the declared registry file, accepting only a file inside the package.
     *
     * Both sides are compared as real paths: the lexical check alone lets a symlink out of the
     * package, and the install path of a Composer path repository is itself a symlink.
     *
     * @return Path|null Null when the file must not be read; the reason is already reported.
     */
    private function registryFile(string $name, string $registry, Path $installPath): ?Path
    {
        $packageDir = \realpath((string) $installPath);
        $file = Path::create($registry)->isAbsolute() ? null : \realpath((string) $installPath->join($registry));

        if ($packageDir === false || $file === false) {
            $this->logger->warning(
                'Package `%s`: registry file `%s` not found. Make sure it is not excluded from the package dist.',
                $name,
                $registry,
            );
            return null;
        }

        if ($file === null || !Path::create($file)->isWithin(Path::create($packageDir))) {
            $this->logger->warning('Package `%s`: registry file `%s` is outside the package, skipped.', $name, $registry);
            return null;
        }

        if ($this->configFile !== null && $file === \realpath((string) $this->configFile)) {
            return null;
        }

        return Path::create($file);
    }

    /**
     * @return list<Software> Valid definitions of the `<registry>` section; invalid ones are reported and skipped.
     */
    private function readRegistry(string $name, Path $file): array
    {
        $xml = (string) @\file_get_contents((string) $file);

        $previous = \libxml_use_internal_errors(true);
        try {
            $document = \simplexml_load_string($xml);
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previous);
        }

        if ($document === false) {
            $this->logger->warning('Package `%s`: registry file `%s` is not a valid XML, skipped.', $name, (string) $file);
            return [];
        }

        if ($document->xpath('/dload/registry') === []) {
            $this->logger->warning('Package `%s`: registry file `%s` has no `<dload><registry>` section.', $name, (string) $file);
            return [];
        }

        // A container of its own, without the application logger: a failure to hydrate an attribute
        // would be logged with no package name. A required attribute it fails on is reported below.
        $registry = new CustomSoftwareRegistry();
        (new ConfigInflector(xml: $xml))->inflect($registry, new ObjectContainer());

        $result = [];
        foreach ($registry->software as $software) {
            $problem = self::problemOf($software);
            if ($problem === null) {
                $result[] = $software;
                continue;
            }

            $this->logger->warning('Package `%s`: software definition skipped, %s.', $name, $problem);
        }

        return $result;
    }

    /**
     * @return array|null Decoded object, or null when the file is missing; an unreadable file is reported.
     */
    private function readJson(Path $file): ?array
    {
        if (!$file->exists()) {
            return null;
        }

        try {
            /** @var mixed $json */
            $json = \json_decode((string) \file_get_contents((string) $file), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('Skipping registries of Composer packages: `%s` is not valid JSON: %s', (string) $file, $e->getMessage());
            return null;
        }

        if (!\is_array($json)) {
            $this->logger->warning('Skipping registries of Composer packages: `%s` is not a JSON object.', (string) $file);
            return null;
        }

        return $json;
    }
}
