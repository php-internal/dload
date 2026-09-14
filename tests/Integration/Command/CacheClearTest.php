<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Command;

use Internal\DLoad\Command\CacheClear;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Registry\Internal\FileRegistryStorage;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Group('integration')]
#[Covers(CacheClear::class)]
final class CacheClearTest
{
    private string $directory;
    private FileRegistryStorage $storage;

    #[Test]
    public function forgetsOnlyTheRepositoriesOfTheNamedSoftware(): void
    {
        $this->seed('a/b', ['rr']);
        $this->seed('c/d', ['temporal', 'tctl']);
        $this->seed('e/f', ['dolt']);

        $tester = $this->run(['software' => ['rr', 'tctl']]);

        Assert::string($tester->getDisplay())->contains('2 repository listing(s) removed.');
        Assert::same(self::uris($this->storage), ['e/f']);
    }

    #[Test]
    public function unknownSoftwareRemovesNothing(): void
    {
        $this->seed('a/b', ['rr']);

        $tester = $this->run(['software' => ['unknown']]);

        Assert::string($tester->getDisplay())->contains('0 repository listing(s) removed.');
        Assert::same(self::uris($this->storage), ['a/b']);
    }

    #[Test]
    public function clearsEverythingWithoutAskingWhenNotInteractive(): void
    {
        $this->seed('a/b', ['rr']);

        $tester = $this->run([], interactive: false);

        Assert::string($tester->getDisplay())->contains('has been cleared');
        Assert::same(self::uris($this->storage), []);
    }

    #[Test]
    public function clearingEverythingInteractivelyRequiresConfirmation(): void
    {
        $this->seed('a/b', ['rr']);

        $declined = $this->run([], interactive: true, answers: ['n']);
        Assert::string($declined->getDisplay())->contains('left as it is');
        Assert::same(self::uris($this->storage), ['a/b']);

        $confirmed = $this->run([], interactive: true, answers: ['y']);
        Assert::string($confirmed->getDisplay())->contains('has been cleared');
        Assert::same(self::uris($this->storage), []);
    }

    #[Test]
    public function forceSkipsTheConfirmation(): void
    {
        $this->seed('a/b', ['rr']);

        $tester = $this->run(['--force' => true], interactive: true);

        Assert::string($tester->getDisplay())->contains('has been cleared');
        Assert::same(self::uris($this->storage), []);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->directory = \sys_get_temp_dir() . '/dload-cache-clear-' . \bin2hex(\random_bytes(6));
        $this->storage = new FileRegistryStorage(Path::create($this->directory), new Logger());
        \mkdir($this->directory, recursive: true);
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
     * @return list<non-empty-string>
     */
    private static function uris(FileRegistryStorage $storage): array
    {
        $uris = \array_map(
            static fn(RepositoryRecord $record): string => $record->id->uri,
            \iterator_to_array($storage->all(), false),
        );
        \sort($uris);

        return $uris;
    }

    /**
     * @param non-empty-string $uri
     * @param list<non-empty-string> $software
     */
    private function seed(string $uri, array $software): void
    {
        $this->storage->save(new RepositoryRecord(new RepositoryId('github', $uri), software: $software));
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $answers
     */
    private function run(array $input, bool $interactive = false, array $answers = []): CommandTester
    {
        $application = new Application();
        // Symfony Console 8 renamed `add()` to `addCommand()`
        \method_exists($application, 'addCommand')
            ? $application->addCommand(new CacheClear())
            : $application->add(new CacheClear());

        $tester = new CommandTester($application->find('cache:clear'));
        $answers === [] or $tester->setInputs($answers);
        $tester->execute(
            $input + ['--config' => $this->directory . '/dload.xml'],
            ['interactive' => $interactive],
        );

        return $tester;
    }
}
