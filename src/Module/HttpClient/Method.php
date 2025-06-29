<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\HttpClient;

enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Head = 'HEAD';
    case Options = 'OPTIONS';
    case Trace = 'TRACE';
    case Connect = 'CONNECT';

    public static function fromString(self|string $method): self
    {
        return $method instanceof self
            ? $method
            : (self::tryFrom(\strtoupper($method)) ?? throw new \InvalidArgumentException(
                "Unsupported HTTP method: {$method}",
            ));
    }
}
