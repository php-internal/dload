<?php

declare(strict_types=1);

namespace Internal\DLoad\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Internal\DLoad\Tools\StructArmed\Support\ClassSignatures;

/**
 * A class on a module's surface must not name an internal type in its signature.
 * Internal types are implementation detail; surfacing one in a public or protected
 * parameter, return, or property drags it into the module contract. Only classes under
 * an Internal namespace are exempt: internal-to-internal coupling stays behind the module
 * boundary (and any cross-module leak is caught by [InternalIsUsableOnlyWithinItsPackageRule]).
 *
 * The @internal tag grants no exemption: DLoad is an application, so nearly every class
 * carries it, and the module boundary is drawn by the namespace alone.
 */
final readonly class InternalMustNotLeakIntoPublicSignatureRule implements RuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer) && !\str_contains($classNode->className, self::SEGMENT);
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

        foreach (ClassSignatures::publicTypes($classNode->file, $classNode->shortName()) as $type) {
            if (! \str_contains($type, self::SEGMENT)) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] exposes internal type [%s] in its public signature',
                    $classNode->getType(),
                    $classNode->className,
                    $type,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }
}
