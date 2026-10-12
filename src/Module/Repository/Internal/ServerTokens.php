<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
 * Finds the API token declared for a server in the environment.
 *
 * A token is declared per server in a variable named after its address, see
 * {@see Server::tokenVariable()}, e.g. `DLOAD_TOKEN_GHE_EXAMPLE_COM`.
 *
 * @internal
 */
final class ServerTokens
{
    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly array $environment,
    ) {}

    public function find(Server $server): ?ApiToken
    {
        $variable = $server->tokenVariable();
        $value = $this->environment[$variable] ?? '';

        return $value === '' ? null : new ApiToken($value, $variable);
    }
}
