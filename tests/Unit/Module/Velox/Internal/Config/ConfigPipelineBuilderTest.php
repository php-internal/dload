<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config;

use Internal\Container\ObjectContainer;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Config\Schema\Action\Velox\Plugin;
use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\Velox\ApiClient;
use Internal\DLoad\Module\Velox\Internal\Config\ConfigPipelineBuilder;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Covers(ConfigPipelineBuilder::class)]
final class ConfigPipelineBuilderTest
{
    private ?string $localFile = null;

    #[Test]
    public function everySourceOverridesTheOnesBeforeIt(): void
    {
        $plugins = [self::plugin('http')];
        $action = new VeloxAction();
        $action->plugins = $plugins;
        $action->roadrunnerVersion = 'v2025.1.0';
        $action->debug = true;
        $action->configFile = $this->localFile = self::createTempFile(<<<'TOML'
            [roadrunner]
            ref = "v2024.6.0"

            [github.plugins.http]
            ref = "local"
            TOML);

        $apiClient = \Mockery::mock(ApiClient::class);
        $apiClient->expects('generateConfig')->with($plugins, null, 'v2025.1.0')->andReturn(<<<'TOML'
            [roadrunner]
            ref = "v2024.3.0"

            [github.plugins.http]
            ref = "api"
            owner = "roadrunner-server"

            [debug]
            enabled = false
            TOML);

        $result = $this->pipeline($apiClient, token: 'secret')(new ConfigContext($action, Path::create('/tmp/build')));

        $data = $result->tomlData->getData();
        Assert::same($data['log'], ['level' => 'debug', 'mode' => 'dev']);
        Assert::same($data['github']['plugins']['http'], ['ref' => 'local', 'owner' => 'roadrunner-server']);
        Assert::same($data['roadrunner']['ref'], 'v2025.1.0');
        Assert::same($data['debug']['enabled'], true);
        Assert::same($data['github']['token']['token'], 'secret');
    }

    #[Test]
    public function skippedSourcesDoNotStopTheChain(): void
    {
        $action = new VeloxAction();
        $action->debug = true;

        $apiClient = \Mockery::mock(ApiClient::class);
        $apiClient->expects('generateConfig')->never();

        $result = $this->pipeline($apiClient, token: 'secret')(new ConfigContext($action, Path::create('/tmp/build')));

        Assert::same($result->tomlData->getData(), [
            'log' => ['level' => 'debug', 'mode' => 'dev'],
            'debug' => ['enabled' => true],
            'github' => ['token' => ['token' => 'secret']],
        ]);
    }

    #[AfterTest]
    protected function removeLocalFile(): void
    {
        $this->localFile === null or \unlink($this->localFile);
        $this->localFile = null;
    }

    private static function createTempFile(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'test_velox_');
        \file_put_contents($file, $content);

        return $file;
    }

    private static function plugin(string $name): Plugin
    {
        $plugin = new Plugin();
        $plugin->name = $name;

        return $plugin;
    }

    /**
     * @return callable(ConfigContext): ConfigContext
     */
    private function pipeline(ApiClient $apiClient, ?string $token): callable
    {
        $gitHub = new GitHub();
        $gitHub->token = $token;

        $container = new ObjectContainer();
        $container->set($apiClient, ApiClient::class);
        $container->set($gitHub);

        return (new ConfigPipelineBuilder($container))->build();
    }
}
