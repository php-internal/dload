<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Service\Factoriable;

/**
 * Architecture enumeration.
 *
 * Represents CPU architectures supported by the downloader system.
 *
 * ```php
 * // To get the current system architecture, use DI Container:
 * $arch = $container->get(Architecture::class);
 *
 * // To create an architecture instance from a build configuration name:
 * $arch = Architecture::tryFromBuildName('my-software_amd64');
 * ```
 *
 * @internal
 */
enum Architecture: string implements Factoriable
{
    case X86_64 = 'amd64';
    case ARM_64 = 'arm64';

    private const ERROR_UNKNOWN_ARCH = 'Current architecture `%s` may not be supported.';

    public static function create(Build $config): self
    {
        return self::tryFrom((string) $config->arch) ?? self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        return self::tryFromString(\php_uname('m')) ?? throw new \OutOfRangeException(
            \sprintf(self::ERROR_UNKNOWN_ARCH, \php_uname('m')),
        );
    }

    public static function tryFromString(string $arch): ?self
    {
        return match ($arch) {
            'AMD64', 'amd64', 'x64', 'x86_64', 'win64' => self::X86_64,
            'arm64', 'aarch64' => self::ARM_64,
            default => null,
        };
    }

    public static function tryFromBuildName(string $name): ?self
    {
        if (\preg_match('/(?:\b|_)(amd64|arm64|aarch64|x86_64|x64|win64)(?:\b|_)/i', $name, $matches) !== 1) {
            return null;
        }

        return self::tryFromString(\strtolower($matches[1]));
    }
}
