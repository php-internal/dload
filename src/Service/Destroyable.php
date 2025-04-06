<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

/**
 * Interface for services that need cleanup on destruction.
 *
 * Implementing classes should release resources properly when no longer needed.
 *
 * ```php
 * class FileHandler implements Destroyable
 * {
 *     private $handle;
 *
 *     public function destroy(): void
 *     {
 *         if ($this->handle) {
 *             fclose($this->handle);
 *             $this->handle = null;
 *         }
 *     }
 * }
 * ```
 *
 * @internal
 */
interface Destroyable
{
    /**
     * Performs cleanup before object destruction.
     *
     * Called by the Container when shutting down or releasing services.
     */
    public function destroy(): void;
}
