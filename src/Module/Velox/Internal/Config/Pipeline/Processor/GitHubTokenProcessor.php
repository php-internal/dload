<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;

/**
 * GitHub token processor (step 4).
 *
 * Adds GitHub authentication token to the configuration.
 * Runs when a GitHub token is configured and available.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class GitHubTokenProcessor implements ConfigProcessor
{
    public function __construct(
        private readonly GitHub $gitHub,
    ) {}

    public function __invoke(ConfigContext $context): ConfigContext
    {
        // Early return if no token
        if ($this->gitHub->token === null) {
            return $context;
        }

        $tomlData = $context->tomlData->set('github.token.token', $this->gitHub->token);

        return $context
            ->withTomlData($tomlData)
            ->addMetadata('github_token_applied', true);
    }
}
