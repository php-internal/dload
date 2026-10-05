<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Common\Libc;

/**
 * Traits of an asset read from its file name, beyond the OS and architecture the asset reports.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class AssetName
{
    /**
     * File extensions, archive layers and companion files like checksums and signatures.
     */
    private const EXTENSION = '/\.(?:tar|gz|tgz|xz|txz|bz2|tbz2?|zst|zip|7z|rar|exe|msi|phar|deb|rpm|apk|dmg|pkg|appimage|sha\d*(?:sum)?|md5|asc|sig|pem|txt|json)$/';

    /**
     * Platform tokens: OS, architecture, libc and the vendor part of target triples.
     */
    private const PLATFORM = '/(?<![a-z0-9])(?:x86[_-]64|amd64|arm64|aarch64|x64|win64|win32|windows|linux|darwin|macos|apple|alpine|android|bsd|freebsd|unknown|pc|musl(?:eabi(?:hf)?)?|gnu(?:eabi(?:hf)?)?|glibc)(?![a-z0-9])/';

    /**
     * @param list<non-empty-string> $extras Name tokens that are not a platform, a version or an extension,
     *        like `profile` or `debug`. The tool name is one of them too.
     */
    private function __construct(
        public readonly ?Libc $libc,
        public readonly array $extras,
    ) {}

    public static function fromString(string $name): self
    {
        return new self(
            libc: Libc::tryFromBuildName($name),
            extras: self::extras($name),
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private static function extras(string $name): array
    {
        $name = \strtolower($name);
        do {
            $name = (string) \preg_replace(self::EXTENSION, '', $name, count: $count);
        } while ($count > 0);

        /** @var list<non-empty-string>|false $tokens */
        $tokens = \preg_split('/[-_.\s]+/', (string) \preg_replace(self::PLATFORM, '', $name), flags: \PREG_SPLIT_NO_EMPTY);

        return \array_values(\array_filter(
            $tokens === false ? [] : $tokens,
            static fn(string $token): bool => \preg_match('/^v?\d+$/', $token) !== 1,
        ));
    }
}
