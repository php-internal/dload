<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Environment;

use Internal\DLoad\Service\Factoriable;

/**
 * Architecture enumeration.
 *
 * @internal
 */
enum Architecture: string implements Factoriable
{
    case X86_64 = 'amd64';
    case ARM_64 = 'arm64';

    private const ERROR_UNKNOWN_ARCH = 'Current architecture `%s` may not be supported.';

    public static function create(): static
    {
        return self::fromGlobals();
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
            'AMD64', 'amd64', 'x86', 'x64', 'x86_64' => self::X86_64,
            'arm64', 'aarch64' => self::ARM_64,
            default => null,
        };
    }

    public static function tryFromBuildName(string $name): ?self
    {
        if (\preg_match('/\b(amd64|arm64|aarch64|x86_64|x64|x86)\b/i', $name, $matches)) {
            return null;
        }

        return self::tryFromString(\strtolower($matches[1]));
    }
}
