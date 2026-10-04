<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Command;

use Internal\DLoad\Command\Get;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\Path;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Group('integration')]
#[Covers(Get::class)]
final class GetTest
{
    private string $directory;

    #[Test]
    public function emptyConfigWithoutQuietFails(): void
    {
        Expect::exception(\RuntimeException::class)->withMessage('No software to download.');

        $this->run([]);
    }

    #[Test]
    public function emptyConfigWithQuietSucceedsSilently(): void
    {
        $tester = $this->run(['--quiet' => true]);

        Assert::same($tester->getStatusCode(), Command::SUCCESS);
        Assert::same($tester->getDisplay(), '');
    }

    #[Test]
    public function missingExplicitConfigStillFailsEvenWithQuiet(): void
    {
        // An explicitly named config that does not exist is an error, --quiet or not
        $missing = $this->directory . '/missing.xml';
        Expect::exception(\InvalidArgumentException::class)->withMessage('Configuration file not found: ' . $missing);

        $this->run([
            '--quiet' => true,
            '--config' => $missing,
        ]);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->directory = \sys_get_temp_dir() . '/dload-get-' . \bin2hex(\random_bytes(6));
        \mkdir($this->directory, recursive: true);
        // An empty config: no <actions>, so there is nothing to download
        \file_put_contents($this->directory . '/dload.xml', '<?xml version="1.0"?><dload/>');
        \putenv('DLOAD_CACHE_DIR=' . $this->directory);
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \putenv('DLOAD_CACHE_DIR');
        \is_dir($this->directory) and FS::removeDir(Path::create($this->directory));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function run(array $input): CommandTester
    {
        $application = new Application();
        // Symfony Console 8 renamed `add()` to `addCommand()`
        \method_exists($application, 'addCommand')
            ? $application->addCommand(new Get())
            : $application->add(new Get());

        $tester = new CommandTester($application->find('get'));
        $tester->execute(
            $input + ['--config' => $this->directory . '/dload.xml'],
            ['interactive' => false],
        );

        return $tester;
    }
}
