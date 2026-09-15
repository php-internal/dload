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
    public function storesAnIndexAndOneFilePerSegment(): void
    {
        $storage = $this->storage();

        $storage->save(self::record('github', 'roadrunner-server/roadrunner', self::range(150, 1)));
        $storage->save(self::record('gitlab', 'group/sub/project', ['v2']));

        $repo = $this->directory . '/repositories/github/roadrunner-server/roadrunner';
        Assert::true(\is_file($repo . '/index.json'));
        Assert::true(\is_file($repo . '/releases-0001.json'));
        Assert::true(\is_file($repo . '/releases-0002.json'));
        Assert::true(\is_file($this->directory . '/repositories/gitlab/group/sub/project/index.json'));

        $loaded = $storage->load(new RepositoryId('github', 'roadrunner-server/roadrunner'));
        Assert::same($loaded->count(), 150);
        Assert::same($loaded->releases()[0]->tag, 'v150');
        Assert::same($loaded->releases()[149]->tag, 'v1');
        Assert::same($loaded->software, ['rr']);
    }

    #[Test]
    public function hiddenFlagSurvivesTheRoundTrip(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'owner/repo');

        $storage->save((new RepositoryRecord($id, checkedAt: 1_000))->withHead([
            new ReleaseRecord('draft', 'draft', hidden: true),
            new ReleaseRecord('v1', 'v1'),
        ]));

        $releases = $storage->load($id)?->releases() ?? [];
        Assert::true($releases[0]->hidden);
        Assert::false($releases[1]->hidden);
    }

    #[Test]
    public function segmentsAreReadWhenReachedAndOnlyDirtyOnesAreWritten(): void
    {
        $storage = $this->storage();
        $storage->save(self::record('github', 'owner/repo', self::range(150, 1)));
        $repo = $this->directory . '/repositories/github/owner/repo';

        // A marker in the first segment shows whether the file is rewritten
        $first = \file_get_contents($repo . '/releases-0001.json');
        \file_put_contents($repo . '/releases-0001.json', \str_replace('"v150"', '"v150"  ', $first));

        $loaded = $storage->load(new RepositoryId('github', 'owner/repo'));
        Assert::same($loaded->count(), 150);

        // Appending to the tail opens a new segment after the full one and rewrites nothing else
        $storage->save($loaded->withTail([new ReleaseRecord('v0', 'v0')]));

        Assert::string(\file_get_contents($repo . '/releases-0001.json'))->contains('"v150"  ');
        Assert::string(\file_get_contents($repo . '/releases-0003.json'))->contains('"v0"');
        Assert::same($storage->load(new RepositoryId('github', 'owner/repo'))->count(), 151);
    }

    #[Test]
    public function replacedSegmentFilesAreRemoved(): void
    {
        $storage = $this->storage();
        $storage->save(self::record('github', 'owner/repo', self::range(150, 1)));
        $repo = $this->directory . '/repositories/github/owner/repo';

        // The new release and the head segment of 50 are repacked into one file; the old one is dropped
        $storage->save($storage->load(new RepositoryId('github', 'owner/repo'))->withHead([new ReleaseRecord('v151', 'v151'), new ReleaseRecord('v150', 'v150')]));

        Assert::false(\is_file($repo . '/releases-0001.json'));
        Assert::true(\is_file($repo . '/releases-0002.json'));
        Assert::true(\is_file($repo . '/releases-0003.json'));
        Assert::same($storage->load(new RepositoryId('github', 'owner/repo'))->count(), 151);
    }

    #[Test]
    public function missingAndCorruptedRecordsReadAsNull(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'owner/repo');

        Assert::null($storage->load($id));

        $storage->save(self::record('github', 'owner/repo', ['v1']));
        \file_put_contents($this->directory . '/repositories/github/owner/repo/index.json', '{not json');

        Assert::null($storage->load($id));
    }

    #[Test]
    public function recordWithAMissingSegmentFileReadsAsNull(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'owner/repo');
        $storage->save(self::record('github', 'owner/repo', ['v1']));

        \unlink($this->directory . '/repositories/github/owner/repo/releases-0001.json');

        Assert::null($storage->load($id));
    }

    #[Test]
    public function corruptedSegmentDropsTheRecordWhenReached(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'owner/repo');
        $storage->save(self::record('github', 'owner/repo', ['v1']));
        \file_put_contents($this->directory . '/repositories/github/owner/repo/releases-0001.json', '[{"name": "no tag"}]');

        $loaded = $storage->load($id);
        Assert::notNull($loaded);

        try {
            $loaded->releases();
            Assert::fail('An unreadable segment must be reported.');
        } catch (\RuntimeException $e) {
            Assert::string($e->getMessage())->contains('unreadable');
            Assert::false(\is_dir($this->directory . '/repositories/github/owner/repo'));
        }
    }

    #[Test]
    public function saveReportsAnUnwritableDirectory(): void
    {
        $storage = $this->storage();
        // A file where the repository directory should be
        \mkdir($this->directory . '/repositories/github', recursive: true);
        \file_put_contents($this->directory . '/repositories/github/owner', 'not a directory');

        try {
            $storage->save(self::record('github', 'owner/repo', ['v1']));
            Assert::fail('A failed write must be reported.');
        } catch (\RuntimeException $e) {
            Assert::string($e->getMessage())->contains('registry');
        }
    }

    #[Test]
    public function staleTemporaryFilesAreCleanedUp(): void
    {
        $storage = $this->storage();
        $storage->save(self::record('github', 'owner/repo', ['v1']));
        $repo = $this->directory . '/repositories/github/owner/repo';

        \file_put_contents($repo . '/index.json.123.tmp', '{}');
        \touch($repo . '/index.json.123.tmp', \time() - 7200);
        \file_put_contents($repo . '/index.json.456.tmp', '{}');

        $storage->save($storage->load(new RepositoryId('github', 'owner/repo'))->withSoftware('rr2'));

        Assert::false(\is_file($repo . '/index.json.123.tmp'));
        Assert::true(\is_file($repo . '/index.json.456.tmp'));
    }

    #[Test]
    public function saveWaitsForTheLockOfAnotherRunAndGivesUp(): void
    {
        $storage = $this->storage(lockTimeout: 0.2);
        $storage->save(self::record('github', 'owner/repo', ['v1']));

        // Another run holds the repository
        $lock = \fopen($this->directory . '/locks/github_owner_repo.lock', 'c');
        \flock($lock, \LOCK_EX);

        $started = \microtime(true);
        try {
            $storage->save(self::record('github', 'owner/repo', ['v2', 'v1']));
            Assert::fail('A lock held by another run must be reported.');
        } catch (\RuntimeException $e) {
            Assert::string($e->getMessage())->contains('Another run holds');
            Assert::true(\microtime(true) - $started >= 0.2);
        }

        // The record was left untouched, and the lock is taken as soon as it is released
        Assert::same($storage->load(new RepositoryId('github', 'owner/repo'))?->count(), 1);
        \flock($lock, \LOCK_UN);
        \fclose($lock);
        $storage->save(self::record('github', 'owner/repo', ['v2', 'v1']));
        Assert::same($storage->load(new RepositoryId('github', 'owner/repo'))?->count(), 2);
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
        Assert::false(\is_dir($this->directory . '/repositories/github/a'));
        Assert::true(\is_dir($this->directory . '/repositories/github/c/d'));

        $storage->clear();
        Assert::count(\iterator_to_array($storage->all(), false), 0);
        Assert::null($storage->load(new RepositoryId('github', 'c/d')));
        Assert::false(\is_dir($this->directory . '/locks'));
    }

    #[Test]
    public function unsafePathSegmentsAreSanitized(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', '../owner/re po:x');

        $storage->save(new RepositoryRecord($id));

        Assert::false(\is_dir(\dirname($this->directory) . '/owner'));
        Assert::true(\is_file($this->directory . '/repositories/github/_/owner/re_po_x/index.json'));
        Assert::true($storage->load($id)?->id->equals($id) ?? false);
    }

    #[Test]
    public function recordOfAnotherRepositoryInTheSameDirectoryIsIgnored(): void
    {
        // Two identities sanitize to one directory name
        $storage = $this->storage();
        $storage->save(self::record('github', 'owner/re?po', ['v1']));

        Assert::null($storage->load(new RepositoryId('github', 'owner/re*po')));
        Assert::notNull($storage->load(new RepositoryId('github', 'owner/re?po')));
    }

    #[Test]
    public function windowsDeviceNamesAreEscaped(): void
    {
        $storage = $this->storage();
        $id = new RepositoryId('github', 'nul/com1');

        $storage->save(self::record('github', 'nul/com1', ['v1']));

        Assert::true(\is_file($this->directory . '/repositories/github/_nul/_com1/index.json'));
        Assert::same($storage->load($id)?->releases()[0]->tag, 'v1');
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
     * @param list<non-empty-string> $tags Newest first.
     */
    private static function record(string $type, string $uri, array $tags): RepositoryRecord
    {
        return (new RepositoryRecord(id: new RepositoryId($type, $uri), checkedAt: 1_000, software: ['rr']))
            ->withHead(\array_map(static fn(string $tag): ReleaseRecord => new ReleaseRecord($tag, $tag), $tags));
    }

    /**
     * @return list<non-empty-string> `v<from>` down to `v<to>`.
     */
    private static function range(int $from, int $to): array
    {
        return \array_map(static fn(int $i): string => 'v' . $i, \range($from, $to));
    }

    private function storage(float $lockTimeout = 10.0): FileRegistryStorage
    {
        return new FileRegistryStorage(Path::create($this->directory), new Logger(), $lockTimeout);
    }
}
