<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary;

use Internal\DLoad\Module\Common\FileSystem\Path;

/**
 * Represents a binary executable with operations for version checking.
 */
interface Binary
{
    /**
     * Gets the binary name.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    public function getPath(): Path;

    /**
     * Checks if the binary exists at its path.
     */
    public function exists(): bool;

    /**
     * Gets the binary version.
     *
     * @return non-empty-string|null Version string or null if not available
     */
    public function getVersion(): ?string;

    /**
     * Checks if the binary version satisfies a constraint.
     *
     * @param non-empty-string $versionConstraint Version constraint (e.g., "^1.2.3")
     *
     * @return bool|null True if the version satisfies the constraint,
     *         false if it doesn't, null if the version is not available
     */
    public function satisfiesVersion(string $versionConstraint): ?bool;

    /**
     * Gets the size of the binary in bytes.
     *
     * @return int|null Size in bytes or null if the binary doesn't exist
     */
    public function getSize(): ?int;

    /**
     * Gets the last modification time of the binary.
     *
     * @return \DateTimeImmutable|null Modification time or null if the binary doesn't exist
     */
    public function getMTime(): ?\DateTimeImmutable;
}
