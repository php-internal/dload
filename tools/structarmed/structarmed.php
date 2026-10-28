<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Preset\Presets\PerPreset;
use Boundwize\StructArmed\Preset\Presets\YagniPreset;
use Boundwize\StructArmed\Rule\Rules\Layer\MayNotDependOnRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotCallFunctionRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotUseLanguageConstructRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotUseSuperglobalsRule;
use Internal\DLoad\Tools\StructArmed\Rule\InternalClassMustBeFinalRule;
use Internal\DLoad\Tools\StructArmed\Rule\InternalIsUsableOnlyWithinItsPackageRule;
use Internal\DLoad\Tools\StructArmed\Rule\InternalMustNotLeakIntoPublicSignatureRule;
use Internal\DLoad\Tools\StructArmed\Rule\InternalNamespaceMustBeAnnotatedInternalRule;
use Internal\DLoad\Tools\StructArmed\Rule\ModulesMustNotDependCyclicallyRule;

// Paths resolve against the project root: `composer structarmed` runs from there.
// Binary belongs here: the public `DloadResult` hands out a `Binary`.
$kernel = ['src/Module/Binary', 'src/Module/Common', 'src/Module/Config', 'src/Module/Version', 'src/Service', 'src/Info.php'];

$architecture = Architecture::define()
    // Defining Source ourselves keeps StructArmed from synthesising it from composer.json,
    // which would drag tests/ into the scan.
    ->layer('Source', ['src'])
    // Shared building blocks every module may use: they must not reach into a module.
    ->layer('Kernel', $kernel)
    ->layer('Modules', ['src/Module'], $kernel)
    // The CLI shell: commands, the facade, and the container wiring.
    ->layer('App', ['src/Command', 'src/Bootstrap.php', 'src/DLoad.php'])
    ->withPresets(
        Preset::PSR4(sourcePaths: ['src']),
        Preset::PER(sourcePaths: ['src']),
        Preset::YAGNI(sourcePaths: ['src']),
        Preset::CODEQUALITY(sourcePaths: ['src']),
    )
    ->rule('kernel.must_not_depend_on_modules', new MayNotDependOnRule('Kernel', 'Modules'))
    ->rule('kernel.must_not_depend_on_app', new MayNotDependOnRule('Kernel', 'App'))
    ->rule('modules.must_not_depend_on_app', new MayNotDependOnRule('Modules', 'App'))
    ->rule('modules.must_not_depend_cyclically', new ModulesMustNotDependCyclicallyRule('Modules', 'Internal\DLoad\Module'))
    ->rule('internal.namespace_must_be_annotated_internal', new InternalNamespaceMustBeAnnotatedInternalRule('Source'))
    ->rule('internal.class_must_be_final', new InternalClassMustBeFinalRule('Source'))
    ->rule('internal.must_not_leak_into_public_signature', new InternalMustNotLeakIntoPublicSignatureRule('Source'))
    ->rule('internal.is_usable_only_within_its_module', new InternalIsUsableOnlyWithinItsPackageRule('Source', [
        // The composition root binds module interfaces to their internal implementations.
        'Internal\DLoad\Bootstrap' => ['Internal\DLoad\Module'],
        // The schema declares its XML/env/CLI mapping with Common's inflection attributes.
        'Internal\DLoad\Module\Config\Schema' => ['Internal\DLoad\Module\Common\Internal\Attribute'],
    ]))
    ->rule('source.must_not_use_superglobals', new MayNotUseSuperglobalsRule('Source'))
    ->skip([
        // Case names spell the architecture identifiers used in release asset names.
        PerPreset::ENUM_CASES_MUST_BE_PASCAL_CASE => ['src/Module/Common/Architecture.php'],
        // A concrete fallback for hosts without a dedicated subclass; tests throw it directly.
        YagniPreset::EXTENDED_CLASS_MUST_BE_ABSTRACT_OR_INSTANTIATED => ['src/Module/Repository/Exception/RateLimitException.php'],
    ])
    // Known debt: module cycles and upward dependencies that need a refactoring of their own.
    ->baseline('tools/structarmed/baseline.php');

foreach (['exit', 'eval'] as $construct) {
    $architecture->rule("source.must_not_use_{$construct}", new MayNotUseLanguageConstructRule('Source', $construct));
}

// Debug helpers of symfony/var-dumper and buggregator/trap must not ship.
foreach (['dump', 'dd', 'var_dump', 'trap', 'tr', 'td'] as $function) {
    $architecture->rule("source.must_not_call_{$function}", new MayNotCallFunctionRule('Source', $function));
}

return $architecture;
