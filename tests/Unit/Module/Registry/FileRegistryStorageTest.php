<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Registry\Internal\FileRegistryStorage;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(FileRegistryStorage::class)]
final class FileRegistryStorageTest
{
    private string $directory;

    #[Test]
    public function storesOneReadableFilePerRepository(): void
    {
        $storage = $this->storage();

        $storage->save(self::record('github', 'roadrunner-server/roadrunner', ['v1']));
        $storage->save(self::record('gitlab', 'group/sub/project', ['v2']));

        Assert::true(\is_file($this->directory . '/repositories/github/roadrunner-server/roadrunner.json'));
        Assert::true(\is_file($this->directory . '/repositories/gitlab/group/sub/project.json'));

        $loaded = $storage->load(new RepositoryId('github', 'roadrunner-server/roadrunner'));
        Assert::same($loaded->releases()[0]->tag, 'v1');
        Assert::same($loaded->software, ['rr']);
    }

    #[Test]
    public function missingAndCorruptedRecordsReadAsNull(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'owner/repo');

        Assert::null($storage->load($id));

        $storage->save(self::record('github', 'owner/repo', ['v1']));
        \file_put_contents($this->directory . '/repositories/github/owner/repo.json', '{not json');

        Assert::null($storage->load($id));
    }

    #[Test]
    public function listsRemovesAndClears(): void
    {
        $storage = $this->storage();
        $storage->save(self::record('github', 'a/b', ['v1']));
        $storage->save(self::record('github', 'c/d', ['v1']));

        Assert::count(\iterator_to_array($storage->all(), false), 2);

        $storage->remove(new RepositoryId('github', 'a/b'));
        Assert::count(\iterator_to_array($storage->all(), false), 1);

        $storage->clear();
        Assert::count(\iterator_to_array($storage->all(), false), 0);
        Assert::null($storage->load(new RepositoryId('github', 'c/d')));
    }

    #[Test]
    public function unsafePathSegmentsAreSanitized(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', '../owner/re po:x');

        $storage->save(new RepositoryRecord($id));

        Assert::false(\is_dir(\dirname($this->directory) . '/owner'));
        Assert::true(\is_file($this->directory . '/repositories/github/_/owner/re_po_x.json'));
        Assert::true($storage->load($id)?->id->equals($id) ?? false);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->directory = \sys_get_temp_dir() . '/dload-registry-' . \bin2hex(\random_bytes(6));
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \is_dir($this->directory) and FS::removeDir(Path::create($this->directory));
    }

    /**
     * @param non-empty-string $type
     * @param non-empty-string $uri
     * @param list<non-empty-string> $tags
     */
    private static function record(string $type, string $uri, array $tags): RepositoryRecord
    {
        return new RepositoryRecord(
            id: new RepositoryId($type, $uri),
            checkedAt: 1_000,
            software: ['rr'],
            releases: \array_map(static fn(string $tag): ReleaseRecord => new ReleaseRecord($tag, $tag), $tags),
        );
    }

    private function storage(): FileRegistryStorage
    {
        return new FileRegistryStorage($this->directory, new Logger());
    }
}
