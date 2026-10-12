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
     * macOS build for every Mac architecture, like `darwin-universal` or `macOS_all`.
     *
     * `universal` and `all` count only next to the macOS token: elsewhere they are ordinary words.
     */
    private const UNIVERSAL = '/(?<![a-z0-9])(?:(?:darwin|macos)[-_.](?:universal2?|all)|(?:universal2?|all)[-_.](?:darwin|macos))(?![a-z0-9])/';

    /**
     * x86-64 microarchitecture level right after the architecture, like `amd64_v3` or `x86-64-v2`,
     * but not a version there, like `amd64-v2.1.0`.
     */
    private const LEVEL = '/(?<![a-z0-9])(?:amd64|x86[_-]64|x64)[-_]v([1-4])(?![a-z0-9]|\.\d)/';

    /**
     * @param bool $universal Whether the asset is a macOS build for every Mac architecture.
     * @param int<1, 4> $level x86-64 microarchitecture level the build requires; 1 when the name has none.
     * @param list<non-empty-string> $extras Name tokens that are not a platform, a version or an extension,
     *        like `profile` or `debug`. The tool name is one of them too.
     */
    private function __construct(
        public readonly ?Libc $libc,
        public readonly bool $universal,
        public readonly int $level,
        public readonly array $extras,
    ) {}

    public static function fromString(string $name): self
    {
        $lower = \strtolower($name);

        return new self(
            libc: Libc::tryFromBuildName($name),
            universal: \preg_match(self::UNIVERSAL, $lower) === 1,
            level: self::level($lower),
            extras: self::extras($lower),
        );
    }

    /**
     * @return int<1, 4>
     */
    private static function level(string $name): int
    {
        \preg_match(self::LEVEL, $name, $matches);

        return match ($matches[1] ?? null) {
            '2' => 2,
            '3' => 3,
            '4' => 4,
            default => 1,
        };
    }

    /**
     * @return list<non-empty-string>
     */
    private static function extras(string $name): array
    {
        do {
            $name = (string) \preg_replace(self::EXTENSION, '', $name, count: $count);
        } while ($count > 0);

        /** @var list<non-empty-string>|false $tokens */
        $tokens = \preg_split(
            '/[-_.\s]+/',
            (string) \preg_replace([self::UNIVERSAL, self::PLATFORM], '', $name),
            flags: \PREG_SPLIT_NO_EMPTY,
        );

        // The version filter also drops a microarchitecture level like `v3`, which has its own rank
        return \array_values(\array_filter(
            $tokens === false ? [] : $tokens,
            static fn(string $token): bool => \preg_match('/^v?\d+$/', $token) !== 1,
        ));
    }
}
