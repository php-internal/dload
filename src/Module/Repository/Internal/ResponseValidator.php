<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Exception\AccessDeniedException;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\AssetNotFoundException;
use Internal\DLoad\Module\Repository\Exception\AuthenticationException;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\Exception\RepositoryNotFoundException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns unsuccessful API responses into exceptions with actionable messages.
 *
 * Without such a check a failed API call is indistinguishable from an empty release list,
 * which hides the real reason (invalid token, exhausted rate limit, missing repository, etc.).
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
abstract class ResponseValidator
{
    /** Maximum length of an API message quoted in an exception message. */
    private const MESSAGE_MAX_LENGTH = 300;

    /**
     * @param bool $authenticated Whether an API token is configured.
     */
    public function __construct(
        protected readonly bool $authenticated,
    ) {}

    /**
     * Throws a descriptive exception if the response is not successful.
     *
     * @throws RepositoryException
     */
    public function validate(RequestInterface $request, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 400) {
            return;
        }

        $apiMessage = self::extractApiMessage($response);
        $repository = $this->repositoryFromUri((string) $request->getUri());
        $endpoint = \sprintf('%s %s', $request->getMethod(), $request->getUri());

        if ($this->isRateLimited($status, $response, $apiMessage)) {
            throw $this->createRateLimitException($response, $apiMessage, $repository);
        }

        throw match (true) {
            $status === 401 => new AuthenticationException(
                $this->authenticationMessage($apiMessage),
                $repository,
            ),
            $status === 403 => new AccessDeniedException(
                $this->accessDeniedMessage($apiMessage, $repository),
                $repository,
            ),
            // A missing asset is not a missing repository: the listing was fine, the file is gone
            $status === 404 && $this->isAssetUri((string) $request->getUri()) => new AssetNotFoundException(
                \sprintf(
                    '%s asset is no longer available: HTTP 404 for %s. '
                    . 'The release may have been deleted or its assets replaced since the release list was fetched.',
                    $this->providerName(),
                    (string) $request->getUri(),
                ),
                $repository,
            ),
            $status === 404 => new RepositoryNotFoundException(
                $this->notFoundMessage($apiMessage, $repository, $endpoint),
                $repository,
            ),
            $status >= 500 => new ApiException(
                \sprintf(
                    '%s API is unavailable: HTTP %d %s (%s).%s Try again later.',
                    $this->providerName(),
                    $status,
                    $response->getReasonPhrase(),
                    $endpoint,
                    $apiMessage === null ? '' : ' ' . $apiMessage,
                ),
                $repository,
            ),
            default => new ApiException(
                \sprintf(
                    '%s API request failed with HTTP %d %s (%s).%s',
                    $this->providerName(),
                    $status,
                    $response->getReasonPhrase(),
                    $endpoint,
                    $apiMessage === null ? '' : ' ' . $apiMessage,
                ),
                $repository,
            ),
        };
    }

    /**
     * Wraps a transport-level failure (DNS, TLS, timeout, etc.) into a readable exception.
     */
    public function transportFailure(RequestInterface $request, \Throwable $e): ApiException
    {
        return new ApiException(
            \sprintf(
                'Failed to reach %s API (%s %s): %s',
                $this->providerName(),
                $request->getMethod(),
                $request->getUri(),
                $e->getMessage(),
            ),
            $this->repositoryFromUri((string) $request->getUri()),
            $e,
        );
    }

    /**
     * @return non-empty-string Provider display name, e.g. `GitHub`.
     */
    abstract protected function providerName(): string;

    /**
     * @return non-empty-string Environment variable that holds the API token.
     */
    abstract protected function tokenEnvVariable(): string;

    /**
     * @return non-empty-string How the provider calls a repository, e.g. `repository` or `project`.
     */
    protected function repositoryTerm(): string
    {
        return 'repository';
    }

    /**
     * Extracts a repository identifier from an API URL for better error messages.
     *
     * @return non-empty-string|null
     */
    abstract protected function repositoryFromUri(string $uri): ?string;

    /**
     * Whether the URI points to a release asset rather than to the API.
     *
     * A 404 for an asset means the release is gone, not that the repository does not exist.
     */
    abstract protected function isAssetUri(string $uri): bool;

    /**
     * @return positive-int|null Requests per hour allowed without a token.
     */
    abstract protected function anonymousRateLimit(): ?int;

    /**
     * @return positive-int|null Requests per hour allowed with a token.
     */
    abstract protected function authenticatedRateLimit(): ?int;

    /**
     * Creates a provider specific rate limit exception.
     *
     * @param non-empty-string $message
     * @param non-empty-string|null $repository
     */
    abstract protected function instantiateRateLimitException(
        string $message,
        ?string $repository,
        ?\DateTimeImmutable $resetAt,
    ): RateLimitException;

    /**
     * Detects a rate limit response.
     *
     * Rate limiting is reported inconsistently: HTTP 429, or HTTP 403 with an exhausted
     * `x-ratelimit-remaining` header, or HTTP 403 with a message about primary/secondary limits.
     */
    protected function isRateLimited(int $status, ResponseInterface $response, ?string $apiMessage): bool
    {
        if ($status === 429) {
            return true;
        }

        if ($status !== 403) {
            return false;
        }

        return $response->getHeaderLine('x-ratelimit-remaining') === '0'
            || $response->getHeaderLine('ratelimit-remaining') === '0'
            || ($apiMessage !== null && \str_contains(\strtolower($apiMessage), 'rate limit'));
    }

    /**
     * @param non-empty-string|null $repository
     */
    protected function createRateLimitException(
        ResponseInterface $response,
        ?string $apiMessage,
        ?string $repository,
    ): RateLimitException {
        $resetAt = self::resetTime($response);
        $isSecondary = $apiMessage !== null && \str_contains(\strtolower($apiMessage), 'secondary rate limit');
        $authenticatedLimit = $this->authenticatedRateLimit();
        $limit = $response->getHeaderLine('x-ratelimit-limit');
        $limit === '' and $limit = (string) ($this->authenticated
            ? $authenticatedLimit
            : $this->anonymousRateLimit());

        $message = \sprintf(
            '%s API %srate limit exceeded%s.',
            $this->providerName(),
            $isSecondary ? 'secondary ' : '',
            $limit === '' ? '' : \sprintf(' (limit: %s requests per hour)', $limit),
        );

        $resetAt === null or $message .= \sprintf(
            ' The limit resets at %s (in %s).',
            $resetAt->format('Y-m-d H:i:s T'),
            self::humanizeInterval($resetAt),
        );

        $apiMessage === null or $message .= \sprintf(' API message: %s', $apiMessage);

        $message .= "\n" . ($this->authenticated
            ? \sprintf(
                'The API token from the %s environment variable has spent its quota: wait for the reset or use another token.',
                $this->tokenEnvVariable(),
            )
            : \sprintf(
                'No API token is configured. Set the %s environment variable to raise the limit%s.',
                $this->tokenEnvVariable(),
                $authenticatedLimit === null
                    ? ''
                    : \sprintf(' up to %d requests per hour', $authenticatedLimit),
            ));

        return $this->instantiateRateLimitException($message, $repository, $resetAt);
    }

    /**
     * @return non-empty-string
     */
    protected function authenticationMessage(?string $apiMessage): string
    {
        return \sprintf(
            "%s API rejected the credentials (HTTP 401%s).\n%s",
            $this->providerName(),
            $apiMessage === null ? '' : ': ' . $apiMessage,
            $this->authenticated
                ? \sprintf(
                    'The API token from the %s environment variable is invalid, expired or revoked. '
                    . 'Provide a valid token or unset the variable to use anonymous access.',
                    $this->tokenEnvVariable(),
                )
                : \sprintf(
                    'No API token is configured, so the request was anonymous. '
                    . 'Set the %s environment variable with a valid token.',
                    $this->tokenEnvVariable(),
                ),
        );
    }

    /**
     * @param non-empty-string|null $repository
     * @return non-empty-string
     */
    protected function accessDeniedMessage(?string $apiMessage, ?string $repository): string
    {
        return \sprintf(
            "%s API denied access%s (HTTP 403%s).\n%s",
            $this->providerName(),
            $repository === null ? '' : \sprintf(' to `%s`', $repository),
            $apiMessage === null ? '' : ': ' . $apiMessage,
            $this->authenticated
                ? \sprintf(
                    'The API token from the %s environment variable has no read access to this %s. '
                    . 'Use a token with read permissions for it.',
                    $this->tokenEnvVariable(),
                    $this->repositoryTerm(),
                )
                : \sprintf(
                    'No API token is configured. Set the %s environment variable with a token '
                    . 'that has read access to this %s.',
                    $this->tokenEnvVariable(),
                    $this->repositoryTerm(),
                ),
        );
    }

    /**
     * @param non-empty-string|null $repository
     * @return non-empty-string
     */
    protected function notFoundMessage(?string $apiMessage, ?string $repository, string $endpoint): string
    {
        return \sprintf(
            "%s API returned HTTP 404 for %s%s.\n%s",
            $this->providerName(),
            $repository === null ? $endpoint : \sprintf('%s `%s`', $this->repositoryTerm(), $repository),
            $apiMessage === null ? '' : ': ' . $apiMessage,
            $this->authenticated
                ? \sprintf(
                    'Check the %1$s address in the configuration. If the %1$s is private, '
                    . 'make sure the token from the %2$s environment variable has read access to it '
                    . '(a 404 is also returned instead of 403 when access is missing).',
                    $this->repositoryTerm(),
                    $this->tokenEnvVariable(),
                )
                : \sprintf(
                    'Check the %1$s address in the configuration. If the %1$s is private, '
                    . 'set the %2$s environment variable with a token that has read access to it.',
                    $this->repositoryTerm(),
                    $this->tokenEnvVariable(),
                ),
        );
    }

    /**
     * Reads a human-readable message from an API error response.
     */
    private static function extractApiMessage(ResponseInterface $response): ?string
    {
        $body = \trim($response->getBody()->__toString());
        if ($body === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Not a JSON response: quote the raw body
            return self::truncate($body);
        }

        $message = match (true) {
            \is_string($decoded) => $decoded,
            \is_array($decoded) => self::messageFromArray($decoded),
            default => null,
        };

        return $message === null ? null : self::truncate($message);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private static function messageFromArray(array $decoded): ?string
    {
        // GitHub: {"message": "...", "documentation_url": "..."}
        // GitLab: {"message": "404 Project Not Found"} or {"error": "..."}
        foreach (['message', 'error', 'error_description'] as $key) {
            /** @var mixed $value */
            $value = $decoded[$key] ?? null;

            if (\is_string($value) && $value !== '') {
                return $value;
            }

            if (!\is_array($value)) {
                continue;
            }

            // GitLab may report a list of messages
            $parts = [];
            /** @var mixed $item */
            foreach ($value as $item) {
                \is_string($item) and $parts[] = $item;
            }

            if ($parts !== []) {
                return \implode(' ', $parts);
            }
        }

        // Older GitHub responses use a plain list: ["API rate limit exceeded...", "https://docs..."]
        /** @var mixed $first */
        $first = $decoded[0] ?? null;

        return \is_string($first) && $first !== '' ? $first : null;
    }

    /**
     * Resolves the moment when a rate limit is reset from response headers.
     */
    private static function resetTime(ResponseInterface $response): ?\DateTimeImmutable
    {
        $reset = $response->getHeaderLine('x-ratelimit-reset');
        if (\preg_match('/^\d+$/', $reset) === 1) {
            return (new \DateTimeImmutable('@' . $reset))->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
        }

        $retryAfter = $response->getHeaderLine('retry-after');
        if (\preg_match('/^\d+$/', $retryAfter) === 1) {
            return new \DateTimeImmutable(\sprintf('+%d seconds', (int) $retryAfter));
        }

        return null;
    }

    /**
     * @return non-empty-string Time left until the given moment, e.g. `42 min 5 sec`.
     */
    private static function humanizeInterval(\DateTimeImmutable $until): string
    {
        $seconds = $until->getTimestamp() - \time();
        if ($seconds <= 0) {
            return 'a moment';
        }

        $minutes = \intdiv($seconds, 60);
        return $minutes === 0
            ? \sprintf('%d sec', $seconds)
            : \sprintf('%d min %d sec', $minutes, $seconds % 60);
    }

    private static function truncate(string $message): string
    {
        $message = \trim(\preg_replace('/\s+/', ' ', $message) ?? $message);

        if (\strlen($message) <= self::MESSAGE_MAX_LENGTH) {
            return $message;
        }

        $cut = \substr($message, 0, self::MESSAGE_MAX_LENGTH);

        // A byte-based cut may split a multibyte UTF-8 character: drop its leftover bytes
        for ($i = 0; $i < 3 && $cut !== '' && \preg_match('//u', $cut) !== 1; ++$i) {
            $cut = \substr($cut, 0, -1);
        }

        return $cut . '…';
    }
}
