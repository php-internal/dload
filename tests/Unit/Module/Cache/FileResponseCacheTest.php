<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Cache;

use Internal\DLoad\Module\Cache\Internal\FileResponseCache;
use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Service\Logger;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(FileResponseCache::class)]
#[Covers(NullResponseCache::class)]
final class FileResponseCacheTest
{
    private string $directory;

    #[Test]
    public function secondCallIsServedFromTheCacheWithItsHeaders(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $fetch = static function () use (&$calls): ResponseInterface {
            ++$calls;
            return new Response(200, ['link' => '<https://api.github.com/x?page=2>; rel="next"'], 'first');
        };

        $first = $cache->remember('https://api.github.com/x', $fetch);
        $second = $cache->remember('https://api.github.com/x', $fetch);

        Assert::same($calls, 1);
        Assert::same((string) $second->getBody(), (string) $first->getBody());
        Assert::same($second->getStatusCode(), 200);

        # The `link` header drives pagination: a cached response without it would look like a
        # single-page listing and the remaining releases would silently disappear.
        Assert::same($second->getHeaderLine('link'), '<https://api.github.com/x?page=2>; rel="next"');
    }

    #[Test]
    public function differentKeysAreCachedApart(): void
    {
        $cache = $this->cache();

        $first = $cache->remember('https://api.github.com/a', static fn(): ResponseInterface => new Response(200, [], 'a'));
        $second = $cache->remember('https://api.github.com/b', static fn(): ResponseInterface => new Response(200, [], 'b'));

        Assert::same((string) $first->getBody(), 'a');
        Assert::same((string) $second->getBody(), 'b');
        Assert::same((string) $cache->remember('https://api.github.com/a', self::unexpectedFetch(...))->getBody(), 'a');
    }

    #[Test]
    public function entryOlderThanTheTtlIsFetchedAgain(): void
    {
        $cache = $this->cache(ttl: 60);
        $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'stale'));

        $this->ageStoredEntries(120);

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'fresh'));

        Assert::same((string) $response->getBody(), 'fresh');
    }

    #[Test]
    public function entryWithinTheTtlIsKept(): void
    {
        $cache = $this->cache(ttl: 600);
        $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'cached'));

        # Freshness must come from the stored timestamp and not from the file mtime: a CI cache
        # restores files with a fresh mtime, which would make every entry look brand new.
        $this->ageStoredEntries(60, touchFiles: true);

        $response = $cache->remember('https://api.github.com/x', self::unexpectedFetch(...));

        Assert::same((string) $response->getBody(), 'cached');
    }

    #[Test]
    public function unsuccessfulResponseIsNotCached(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $fetch = static function () use (&$calls): ResponseInterface {
            ++$calls;
            return new Response(403, [], 'rate limit exceeded');
        };

        $cache->remember('https://api.github.com/x', $fetch);
        $cache->remember('https://api.github.com/x', $fetch);

        Assert::same($calls, 2);
        Assert::same(\glob($this->directory . '/*.json'), []);
    }

    #[Test]
    public function corruptedEntryIsIgnored(): void
    {
        $cache = $this->cache();
        $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'cached'));

        foreach (\glob($this->directory . '/*.json') as $file) {
            \file_put_contents($file, 'not a json payload');
        }

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'refetched'));

        Assert::same((string) $response->getBody(), 'refetched');
    }

    #[Test]
    public function nullCacheAlwaysFetches(): void
    {
        $cache = new NullResponseCache();
        $calls = 0;
        $fetch = static function () use (&$calls): ResponseInterface {
            ++$calls;
            return new Response(200, [], 'body');
        };

        $cache->remember('https://api.github.com/x', $fetch);
        $response = $cache->remember('https://api.github.com/x', $fetch);

        Assert::same($calls, 2);
        Assert::same((string) $response->getBody(), 'body');
    }

    #[Test]
    public function unreadableEntryIsFetchedAgain(): void
    {
        $cache = $this->cache();
        $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'cached'));

        foreach (\glob($this->directory . '/*.json') as $file) {
            \chmod($file, 0000);
            \is_readable($file) and throw new SkipTest('The entry stays readable for this user.');
        }

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'refetched'));

        Assert::same((string) $response->getBody(), 'refetched');
    }

    #[Test]
    public function responseIsReturnedWhenTheDirectoryCannotBeCreated(): void
    {
        \file_put_contents($this->directory, 'a file where the cache directory should be');
        $cache = new FileResponseCache($this->directory . '/entries', 600, new Logger());

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'body'));

        Assert::same((string) $response->getBody(), 'body');
    }

    #[Test]
    public function responseIsReturnedWhenTheEntryCannotBeWritten(): void
    {
        $cache = $this->cache();
        $file = $this->storeAndForget($cache);

        # The entry is written aside first, so an unwritable temporary path is what makes the store fail.
        \mkdir($file . '.' . \getmypid() . '.tmp');

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'body'));

        Assert::same((string) $response->getBody(), 'body');
    }

    #[Test]
    public function responseIsReturnedWhenTheEntryCannotBeMovedIntoPlace(): void
    {
        $cache = $this->cache();
        $file = $this->storeAndForget($cache);

        # A non-empty directory in place of the entry cannot be replaced by a rename.
        \mkdir($file);
        \file_put_contents($file . '/occupied', 'x');

        $response = $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'body'));

        Assert::same((string) $response->getBody(), 'body');
        Assert::same(\file_get_contents($file . '/occupied'), 'x');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->directory = \sys_get_temp_dir() . '/dload-response-cache-' . \bin2hex(\random_bytes(6));
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        self::erase($this->directory);
    }

    /**
     * Removes a file or a directory with everything below it.
     */
    private static function erase(string $path): void
    {
        if (\is_dir($path)) {
            foreach (\glob($path . '/*') as $child) {
                self::erase($child);
            }

            \rmdir($path);
            return;
        }

        \is_file($path) and \unlink($path);
    }

    private static function unexpectedFetch(): ResponseInterface
    {
        throw new \LogicException('The cached entry must be used instead of fetching again.');
    }

    private function cache(int $ttl = 600): FileResponseCache
    {
        return new FileResponseCache($this->directory, $ttl, new Logger());
    }

    /**
     * Stores an entry to learn the path the cache picks for the key, then removes it again, so a
     * test can put an obstacle exactly where the next store writes.
     *
     * @return non-empty-string
     */
    private function storeAndForget(FileResponseCache $cache): string
    {
        $cache->remember('https://api.github.com/x', static fn(): ResponseInterface => new Response(200, [], 'stored'));

        $files = \glob($this->directory . '/*.json');
        Assert::same(\count($files), 1);

        \unlink($files[0]);

        return $files[0];
    }

    /**
     * Moves the stored creation timestamps back in time, so expiry can be tested without sleeping.
     */
    private function ageStoredEntries(int $seconds, bool $touchFiles = false): void
    {
        foreach (\glob($this->directory . '/*.json') as $file) {
            $payload = \json_decode(\file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $payload['created_at'] -= $seconds;
            \file_put_contents($file, \json_encode($payload, JSON_THROW_ON_ERROR));

            $touchFiles and \touch($file);
        }
    }
}
