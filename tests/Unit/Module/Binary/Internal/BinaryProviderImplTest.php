<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Internal\BinaryExecutor;
use Internal\DLoad\Module\Binary\Internal\BinaryProviderImpl;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * The project has `bin/` as the destination and an existing `tool` next to it.
 */
#[Test]
#[Covers(BinaryProviderImpl::class)]
final class BinaryProviderImplTest
{
    private Path $dir;

    public function findsAnExistingLocalBinary(): void
    {
        $binary = self::provider()->getLocalBinary($this->dir->join('bin'), BinaryConfig::fromArray(['name' => 'tool']));

        Assert::same((string) $binary?->getPath(), (string) $this->dir->join('bin', 'tool'));
    }

    #[DataSet(['../tool'], 'a parent traversal')]
    #[DataSet(['..\\tool'], 'a Windows parent traversal')]
    #[DataSet(['{absolute}'], 'an absolute path')]
    public function aNameThatIsAPathFindsNoLocalBinary(string $name): void
    {
        $name === '{absolute}' and $name = (string) $this->dir->join('tool');

        $binary = self::provider()->getLocalBinary($this->dir->join('bin'), BinaryConfig::fromArray(['name' => $name]));

        Assert::null($binary);
    }

    #[DataSet(['{php}'], 'an absolute path')]
    #[DataSet(['../php'], 'a parent traversal')]
    #[DataSet(['bin/php'], 'a subdirectory')]
    public function aLookedUpNameThatIsAPathFindsNoGlobalBinary(string $name): void
    {
        $name === '{php}' and $name = \PHP_BINARY;

        $binary = self::provider()->getGlobalBinary(BinaryConfig::fromArray(['name' => 'php']), $name);

        Assert::null($binary);
    }

    public function aNameThatIsAPathFindsNoGlobalBinary(): void
    {
        $binary = self::provider()->getGlobalBinary(BinaryConfig::fromArray(['name' => \PHP_BINARY]));

        Assert::null($binary);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->dir = Path::create(\sys_get_temp_dir())->join('dload-binary-provider-' . \bin2hex(\random_bytes(6)));
        FS::mkdir($this->dir->join('bin'));
        \touch((string) $this->dir->join('tool'));
        \touch((string) $this->dir->join('bin', 'tool'));
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        FS::remove($this->dir);
    }

    private static function provider(): BinaryProviderImpl
    {
        return new BinaryProviderImpl(OperatingSystem::Linux, new BinaryExecutor(new Logger()));
    }
}
