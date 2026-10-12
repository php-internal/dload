<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\BinaryVersion;
use Internal\DLoad\Module\Binary\Internal\AbstractBinary;
use Internal\DLoad\Module\Binary\Internal\BinaryExecutor;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(AbstractBinary::class)]
final class AbstractBinaryTest
{
    public static function provideVersionOutputs(): \Generator
    {
        yield 'build metadata' => ['App version 2.3.4+20230415', '2.3.4+20230415'];
        yield 'pre-release with build metadata' => ['tool v1.2.3-rc.1+build.5', '1.2.3-rc.1+build.5'];
        yield 'plain version' => ['tool 1.2.3', '1.2.3'];
        yield 'no version' => ['no version here', null];
    }

    #[DataProvider('provideVersionOutputs')]
    #[Test]
    public function versionStringShowsTheWholeVersion(string $output, ?string $expected): void
    {
        $binary = new class('tool', new BinaryConfig(), new BinaryExecutor(new Logger()), $output) extends AbstractBinary {
            public function __construct(
                string $name,
                BinaryConfig $config,
                BinaryExecutor $executor,
                private readonly string $output,
            ) {
                parent::__construct($name, $config, $executor);
            }

            public function getVersion(): BinaryVersion
            {
                return BinaryVersion::fromBinaryOutput($this->output);
            }

            public function getPath(): Path
            {
                return Path::create('tool');
            }
        };

        Assert::same($binary->getVersionString(), $expected);
    }
}
