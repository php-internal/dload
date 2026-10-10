<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Pipeline\Pipeline;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchitectureRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchiveRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\CompanionRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ExtrasRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\FormatRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\LibcRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\NamePatternRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\OperatingSystemRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\PackageRule;
use Internal\DLoad\Module\Repository\AssetInterface;
use Psr\Container\ContainerInterface;

/**
 * Orders the assets of a release from the best fit for the host to the worst.
 *
 * ```php
 * $selection = $selector->select($release->getAssets(), '/^tool-.*$/', Type::Archive, strict: true);
 * $selection->isEmpty() or $best = $selection->assets()[0];
 * ```
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class AssetSelector
{
    /** @var callable(Selection): Selection */
    private $pipeline;

    public function __construct(
        OperatingSystem $operatingSystem,
        Architecture $architecture,
        ContainerInterface $container,
        ArchiveFactory $archiveFactory,
    ) {
        /**
         * Rules run in this order, which is also the priority of their ranks.
         *
         * @see AssetRule::select()
         * @var callable(Selection): Selection $pipeline
         */
        $pipeline = Pipeline::prepare(
            new NamePatternRule(),
            new FormatRule($archiveFactory),
            new CompanionRule(),
            new OperatingSystemRule($operatingSystem),
            new ArchitectureRule($architecture, $operatingSystem),
            // After the platform rules: the packages it removes are then the host's own, which the report names
            new PackageRule(),
            new LibcRule($container),
            new ExtrasRule(),
            new ArchiveRule($archiveFactory),
        )->with(static fn(Selection $selection): Selection => $selection, 'select');
        $this->pipeline = $pipeline;
    }

    /**
     * @param iterable<AssetInterface> $assets
     * @param non-empty-string $assetPattern Pattern the asset names must match.
     * @param Type|null $type Download action type restricting the asset format.
     * @param bool $strict Whether assets for another OS or architecture are removed rather than ranked lower.
     */
    public function select(iterable $assets, string $assetPattern, ?Type $type, bool $strict): Selection
    {
        return ($this->pipeline)(Selection::create($assets, $assetPattern, $type, $strict));
    }
}
