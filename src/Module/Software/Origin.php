<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

/**
 * Where a software entry was declared.
 *
 * ```php
 * (string) Origin::official();             // official
 * (string) Origin::package('acme/app');    // acme/app
 * ```
 */
final readonly class Origin implements \Stringable
{
    /**
     * @param non-empty-string|null $package Composer package name, set only for {@see OriginKind::Package}.
     */
    private function __construct(
        public OriginKind $kind,
        public ?string $package = null,
    ) {}

    public static function config(): self
    {
        return new self(OriginKind::Config);
    }

    public static function official(): self
    {
        return new self(OriginKind::Official);
    }

    /**
     * @param non-empty-string $name Composer package name.
     */
    public static function package(string $name): self
    {
        return new self(OriginKind::Package, \strtolower($name));
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->package === $other->package;
    }

    public function __toString(): string
    {
        return match ($this->kind) {
            OriginKind::Config => 'dload.xml',
            OriginKind::Official => 'official',
            OriginKind::Package => (string) $this->package,
        };
    }
}
