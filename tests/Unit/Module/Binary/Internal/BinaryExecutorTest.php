<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Internal\BinaryExecutor;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * The binary is PHP running a script, in a directory with a space in its name, that prints the
 * arguments it received as JSON.
 */
#[Test]
#[Covers(BinaryExecutor::class)]
final class BinaryExecutorTest
{
    private Path $dir;
    private string $script;

    #[DataSet([[]], 'no arguments')]
    #[DataSet([['version', '--short']], 'two arguments')]
    #[DataSet([['a path/with spaces']], 'an argument with spaces')]
    #[DataSet([['']], 'an empty argument')]
    #[DataSet([['C:\\dir\\', 'next']], 'a trailing backslash')]
    #[DataSet([["it's"]], 'a single quote')]
    #[DataSet([['say "hi"']], 'double quotes')]
    #[DataSet([['--version', '&', 'echo', 'X>{marker}']], 'a command separator')]
    #[DataSet([['--version & echo X>{marker}']], 'a command separator in one argument')]
    #[DataSet([['--version | tee {marker}']], 'a pipe')]
    #[DataSet([['$(echo X>{marker})']], 'a command substitution')]
    #[DataSet([['`echo X>{marker}`']], 'backticks')]
    #[DataSet([['>{marker}']], 'a redirect')]
    #[DataSet([['^&']], 'a cmd escape')]
    #[DataSet([['%PATH%']], 'a cmd variable')]
    #[DataSet([['!PATH!']], 'a delayed expansion variable')]
    #[DataSet([['$HOME']], 'a shell variable')]
    #[DataSet([['*']], 'a glob')]
    #[DataSet([['~']], 'a home directory')]
    public function everyArgumentReachesTheBinaryVerbatim(array $arguments): void
    {
        $marker = (string) $this->dir->join('marker');
        $arguments = \array_map(static fn(string $argument): string => \str_replace('{marker}', $marker, $argument), $arguments);

        $output = (new BinaryExecutor(new Logger()))->execute(Path::create(\PHP_BINARY), $this->script, ...$arguments);

        Assert::same(\json_decode(\implode("\n", $output), true), $arguments);
        Assert::false(\file_exists($marker));
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->dir = Path::create(\sys_get_temp_dir())->join('dload executor ' . \bin2hex(\random_bytes(6)));
        FS::mkdir($this->dir);
        $this->script = (string) $this->dir->join('argv.php');
        \file_put_contents($this->script, '<?php echo json_encode(array_slice($argv, 1));');
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        FS::remove($this->dir);
    }
}
