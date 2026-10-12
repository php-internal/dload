<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\BinaryVersion;
use Internal\DLoad\Module\Binary\Internal\AbstractBinary;
use Internal\DLoad\Module\Binary\Internal\BinaryExecutor;
use Internal\DLoad\Module\Binary\Internal\LocalBinary;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
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

    #[Test]
    #[DataSet([['build', '-c', '{dir}/velox config.toml', '-o', '{dir}/out dir']], 'paths with spaces')]
    #[DataSet([["it's", 'a&b', '*']], 'shell syntax')]
    public function executePassesEachArgumentToTheBinaryOnce(array $arguments): void
    {
        $dir = self::directory('dload binary ');
        $arguments = \array_map(static fn(string $argument): string => \str_replace('{dir}', $dir, $argument), $arguments);
        $script = $dir . \DIRECTORY_SEPARATOR . 'argv.php';
        \file_put_contents($script, '<?php echo json_encode(array_slice($argv, 1));');

        try {
            $output = self::php(new BinaryConfig())->execute($script, ...$arguments);
        } finally {
            FS::remove(Path::create($dir));
        }

        Assert::same(\json_decode(\implode("\n", $output), true), $arguments);
    }

    #[Test]
    #[DataSet(['version --short', '1.2.3'], 'two arguments')]
    #[DataSet(["  version \t --short ", '1.2.3'], 'extra whitespace')]
    #[DataSet(['version', '9.9.9'], 'one argument')]
    public function getVersionSplitsTheVersionCommandIntoArguments(string $command, string $version): void
    {
        $dir = self::directory('dload-binary-');
        $script = $dir . \DIRECTORY_SEPARATOR . 'version.php';
        \file_put_contents(
            $script,
            '<?php echo array_slice($argv, 1) === ["version", "--short"] ? "tool 1.2.3" : "tool 9.9.9";',
        );
        \preg_match('/\s/', $script) === 1 and throw new SkipTest('The temp directory path contains spaces.');
        $config = BinaryConfig::fromArray(['name' => 'php', 'version-command' => "$script $command"]);

        try {
            $result = self::php($config)->getVersionString();
        } finally {
            FS::remove(Path::create($dir));
        }

        Assert::same($result, $version);
    }

    private static function php(BinaryConfig $config): LocalBinary
    {
        return new LocalBinary('php', $config, new BinaryExecutor(new Logger()), Path::create(\PHP_BINARY));
    }

    private static function directory(string $prefix): string
    {
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . $prefix . \bin2hex(\random_bytes(6));
        \mkdir($dir);

        return $dir;
    }
}
