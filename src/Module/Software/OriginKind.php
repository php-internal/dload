<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

/**
 * Kind of source a software entry comes from.
 *
 * Cases are listed from the highest priority to the lowest: an entry of an earlier kind shadows
 * an entry with the same id of a later kind.
 */
enum OriginKind
{
    /**
     * The `<registry>` section of the project config.
     */
    case Config;

    /**
     * The registry shipped with DLoad.
     */
    case Official;

    /**
     * A Composer package of the project.
     */
    case Package;

    /**
     * @return int<0, max> Lower is stronger.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Config => 0,
            self::Official => 1,
            self::Package => 2,
        };
    }
}
