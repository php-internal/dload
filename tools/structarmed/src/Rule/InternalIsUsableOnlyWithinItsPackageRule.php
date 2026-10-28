<?php

declare(strict_types=1);

namespace Internal\DLoad\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * An Internal namespace is private to its module: only code living under the same
 * prefix may depend on it. `Internal\DLoad\Module\Registry\Internal\*` is reachable from
 * anywhere in `Internal\DLoad\Module\Registry\`, but not from another module. The owning
 * module is the namespace prefix in front of the `Internal` segment, so the boundary
 * tracks the folder layout without being spelled out module by module.
 */
final readonly class InternalIsUsableOnlyWithinItsPackageRule implements RuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    /**
     * @param array<string, list<string>> $allowed consumer class or namespace prefix => internal
     *        namespace prefixes it may use anyway, for consumers whose job is to reach inside
     *        (a composition root wiring implementations)
     */
    public function __construct(
        private string $layer,
        private array $allowed = [],
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer);
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return $this->evaluateAll($classNode)[0] ?? null;
    }

    /**
     * @return list<RuleViolation>
     */
    public function evaluateAll(ClassNode $classNode): array
    {
        $violations = [];

        foreach ($classNode->dependencies as $dependency) {
            $position = \strpos($dependency, self::SEGMENT);

            if ($position === false) {
                continue;
            }

            $owner = \substr($dependency, 0, $position);

            if (\str_starts_with($classNode->className, $owner . '\\') || $this->isAllowed($classNode->className, $dependency)) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] must not use internal [%s] — it is private to package [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $dependency,
                    $owner,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }

    private function isAllowed(string $consumer, string $dependency): bool
    {
        foreach ($this->allowed as $consumerPrefix => $internalPrefixes) {
            if ($consumer !== $consumerPrefix && !\str_starts_with($consumer, $consumerPrefix . '\\')) {
                continue;
            }

            foreach ($internalPrefixes as $internalPrefix) {
                if ($dependency === $internalPrefix || \str_starts_with($dependency, $internalPrefix . '\\')) {
                    return true;
                }
            }
        }

        return false;
    }
}
