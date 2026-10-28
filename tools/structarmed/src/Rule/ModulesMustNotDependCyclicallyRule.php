<?php

declare(strict_types=1);

namespace Internal\DLoad\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\AbstractLayerAwareRule;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * Modules of a layer form a directed acyclic graph: a module that reaches back to one of
 * its dependants can no longer be understood, tested, or replaced on its own. A module is
 * the namespace segment right after $modulesNamespace.
 *
 * A class is reported once per module it depends on when that dependency closes a cycle,
 * with the cycle path, so every edge of the loop points at a place where it can be cut.
 * Only classes inside the layer count, on both ends: a dependency on a shared kernel
 * outside it never forms a cycle here.
 */
final class ModulesMustNotDependCyclicallyRule extends AbstractLayerAwareRule implements MultipleRuleViolationInterface
{
    /** @var array<string, array<string, true>>|null module => modules it depends on */
    private ?array $graph = null;

    public function __construct(
        private readonly string $layer,
        private readonly string $modulesNamespace,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer) && $this->module($classNode->className) !== null;
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
        $from = (string) $this->module($classNode->className);
        $violations = [];

        foreach ($this->dependencyModules($classNode, $from) as $to) {
            $back = $this->path($to, $from);

            if ($back === null) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] depends on module [%s], closing the module cycle [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $to,
                    \implode(' -> ', [$from, ...$back]),
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }

    private function module(string $className): ?string
    {
        $prefix = $this->modulesNamespace . '\\';

        if (!\str_starts_with($className, $prefix)) {
            return null;
        }

        $module = \strstr(\substr($className, \strlen($prefix)), '\\', true);

        return $module === false || $module === '' ? null : $module;
    }

    /**
     * Other modules of the layer the class depends on, sorted.
     *
     * @return list<string>
     */
    private function dependencyModules(ClassNode $classNode, string $from): array
    {
        $modules = [];

        foreach ($classNode->dependencies as $dependency) {
            $node = $this->getDependencyNode($dependency);

            if ($node === null || !$node->isInLayer($this->layer)) {
                continue;
            }

            $to = $this->module($node->className);
            $to === null || $to === $from or $modules[$to] = true;
        }

        $modules = \array_keys($modules);
        \sort($modules);

        return $modules;
    }

    /**
     * Shortest module path from $start to $goal, breadth-first, both ends included.
     *
     * @return list<string>|null
     */
    private function path(string $start, string $goal): ?array
    {
        $graph = $this->graph ??= $this->buildGraph();
        $previous = [$start => null];
        $queue = [$start];

        while ($queue !== []) {
            $current = \array_shift($queue);

            if ($current === $goal) {
                $path = [];
                for ($node = $goal; $node !== null; $node = $previous[$node]) {
                    $path[] = $node;
                }

                return \array_reverse($path);
            }

            foreach (\array_keys($graph[$current] ?? []) as $next) {
                if (!\array_key_exists($next, $previous)) {
                    $previous[$next] = $current;
                    $queue[] = $next;
                }
            }
        }

        return null;
    }

    /**
     * Built from the whole scanned class map on first use, so it sees every module edge
     * no matter which class is evaluated first.
     *
     * @return array<string, array<string, true>>
     */
    private function buildGraph(): array
    {
        $graph = [];

        foreach ($this->classNodeMap as $node) {
            if (!$this->appliesTo($node)) {
                continue;
            }

            $from = (string) $this->module($node->className);

            foreach ($this->dependencyModules($node, $from) as $to) {
                $graph[$from][$to] = true;
            }
        }

        return $graph;
    }
}
