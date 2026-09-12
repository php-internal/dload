<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Cache;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\Module\Cache\Internal\FileResponseCache;
use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Module\Cache\ResponseCache;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

/**
 * The cache is only useful when the container actually hands out the configured implementation,
 * so the binding is verified through a real bootstrap rather than by constructing it by hand.
 */
#[Group('integration')]
#[Covers(Bootstrap::class)]
final class ResponseCacheBindingTest
{
    #[Test]
    public function cacheIsDisabledWithoutADirectory(): void
    {
        Assert::instanceOf(self::resolve(), NullResponseCache::class);
    }

    #[Test]
    public function environmentVariableEnablesTheFileCache(): void
    {
        Assert::instanceOf(
            self::resolve(environment: ['DLOAD_CACHE_DIR' => \sys_get_temp_dir() . '/dload-cache-binding']),
            FileResponseCache::class,
        );
    }

    #[Test]
    public function xmlAttributeEnablesTheFileCache(): void
    {
        Assert::instanceOf(
            self::resolve(xml: \sprintf(
                '<?xml version="1.0"?><dload cache-dir="%s"/>',
                \sys_get_temp_dir() . '/dload-cache-binding',
            )),
            FileResponseCache::class,
        );
    }

    #[Test]
    public function zeroTtlDisablesTheCache(): void
    {
        Assert::instanceOf(
            self::resolve(environment: [
                'DLOAD_CACHE_DIR' => \sys_get_temp_dir() . '/dload-cache-binding',
                'DLOAD_CACHE_TTL' => '0',
            ]),
            NullResponseCache::class,
        );
    }

    /**
     * @param array<string, string> $environment
     */
    private static function resolve(?string $xml = null, array $environment = []): ResponseCache
    {
        return Bootstrap::init()
            ->withConfig(xml: $xml, environment: $environment)
            ->finish()
            ->get(ResponseCache::class);
    }
}
