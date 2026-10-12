<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

/**
 * Comparison operator of a version constraint, e.g. `>=` of `>=1.2.3`.
 *
 * @internal
 */
enum Operator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case Less = '<';
    case LessOrEqual = '<=';

    /**
     * Resolves an operator of the constraint syntax: none and `==` are {@see Equal}, `<>` is {@see NotEqual}.
     *
     * @return self|null Null when the text is not an operator.
     */
    public static function fromString(string $operator): ?self
    {
        return match ($operator) {
            '', '==' => self::Equal,
            '<>' => self::NotEqual,
            default => self::tryFrom($operator),
        };
    }

    /**
     * @param int $order Order of a version against the bound: negative when the version is lower.
     */
    public function accepts(int $order): bool
    {
        return match ($this) {
            self::Equal => $order === 0,
            self::NotEqual => $order !== 0,
            self::Greater => $order > 0,
            self::GreaterOrEqual => $order >= 0,
            self::Less => $order < 0,
            self::LessOrEqual => $order <= 0,
        };
    }
}
