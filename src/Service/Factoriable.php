<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

/**
 * Interface for classes that can create instances of themselves.
 *
 * Implementing classes should provide a static factory method for instantiation.
 *
 * ```php
 * class Config implements Factoriable
 * {
 *     private function __construct(
 *         private string $path
 *     ) {}
 *
 *     public static function create(string $path): self
 *     {
 *         return new self($path);
 *     }
 * }
 * ```
 *
 * @method static create
 *         Method creates new instance of the class. May contain any injectable parameters.
 *
 * @internal
 */
interface Factoriable {}