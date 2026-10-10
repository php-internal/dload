<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Constraint::class)]
final class ConstraintTest
{
    public static function provideValidConstraints(): \Generator
    {
        // Basic version constraints
        yield 'simple version' => [
            '^2.12.0',
            '^2.12.0',
            null,
            Stability::Stable,
            'Simple version constraint without suffix or stability',
        ];

        yield 'tilde version' => [
            '~1.20.0',
            '~1.20.0',
            null,
            Stability::Stable,
            'Tilde version constraint',
        ];

        yield 'exact version' => [
            '2.12.0',
            '2.12.0',
            null,
            Stability::Stable,
            'Exact version constraint',
        ];

        yield 'greater than or equal' => [
            '>=1.0.0',
            '>=1.0.0',
            null,
            Stability::Stable,
            'Greater than or equal version constraint',
        ];

        yield 'less than' => [
            '<3.0.0',
            '<3.0.0',
            null,
            Stability::Stable,
            'Less than version constraint',
        ];

        // Feature suffix constraints
        yield 'feature suffix' => [
            '^2.12.0-feature',
            '^2.12.0',
            'feature',
            Stability::Preview,
            'Version with feature suffix',
        ];

        yield 'hyphenated feature suffix' => [
            '^2.12.0-my-feature',
            '^2.12.0',
            'my-feature',
            Stability::Preview,
            'Version with hyphenated feature suffix',
        ];

        yield 'single letter feature' => [
            '^2.12.0-x',
            '^2.12.0',
            'x',
            Stability::Preview,
            'Version with single letter feature suffix',
        ];

        // Explicit stability constraints
        yield 'explicit beta stability' => [
            '^2.12.0@beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with explicit beta stability',
        ];

        yield 'explicit alpha stability' => [
            '~1.20.0@alpha',
            '~1.20.0',
            null,
            Stability::Alpha,
            'Version with explicit alpha stability',
        ];

        yield 'explicit stable stability' => [
            '^2.12.0@stable',
            '^2.12.0',
            null,
            Stability::Stable,
            'Version with explicit stable stability',
        ];

        yield 'explicit RC stability' => [
            '^2.12.0@RC',
            '^2.12.0',
            null,
            Stability::RC,
            'Version with explicit RC stability',
        ];

        // Implicit stability constraints (stability keywords as suffixes)
        yield 'implicit beta stability' => [
            '^2.12.0-beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with implicit beta stability',
        ];

        yield 'implicit alpha stability' => [
            '~1.20.0-alpha',
            '~1.20.0',
            null,
            Stability::Alpha,
            'Version with implicit alpha stability',
        ];

        yield 'implicit dev stability' => [
            '^2.12.0-dev',
            '^2.12.0',
            null,
            Stability::Dev,
            'Version with implicit dev stability',
        ];

        yield 'implicit nightly stability' => [
            '^2.12.0-nightly',
            '^2.12.0',
            null,
            Stability::Nightly,
            'Version with implicit nightly stability',
        ];

        // Combined constraints
        yield 'feature with explicit stability' => [
            '^2.12.0-feature@beta',
            '^2.12.0',
            'feature',
            Stability::Beta,
            'Version with feature suffix and explicit stability',
        ];

        yield 'hyphenated feature with stability' => [
            '^2.12.0-my-feature@alpha',
            '^2.12.0',
            'my-feature',
            Stability::Alpha,
            'Version with hyphenated feature suffix and explicit stability',
        ];

        // Complex base versions with feature suffixes
        yield 'complex base with feature' => [
            '^2.12.0-my-beta-feature@stable',
            '^2.12.0',
            'my-beta-feature',
            Stability::Stable,
            'Complex base version with feature suffix',
        ];

        // Edge cases with whitespace
        yield 'constraint with whitespace' => [
            '  ^2.12.0-feature@beta  ',
            '^2.12.0',
            'feature',
            Stability::Beta,
            'Constraint with surrounding whitespace',
        ];

        // Stability at the end of suffix
        yield 'stability at end of suffix' => [
            '^2.12.0-my-feature-beta',
            '^2.12.0',
            'my-feature',
            Stability::Beta,
            'Stability keyword at the end of suffix',
        ];

        // Case sensitivity tests
        yield 'uppercase stability explicit' => [
            '^2.12.0@BETA',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with uppercase explicit stability',
        ];

        yield 'mixed case stability implicit' => [
            '^2.12.0-Beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with mixed case implicit stability',
        ];

        // Multiple dashes in base version
        yield 'base with multiple dashes' => [
            '^2.12.0-alpha.1-feature',
            '^2.12.0',
            'alpha.1-feature',
            Stability::Preview,
            'Base version with multiple dashes and feature suffix',
        ];

        yield 'feature suffix starting with number' => [
            '^2.12.0-1feature',
            '^2.12.0',
            '1feature',
            Stability::Preview,
            'Feature suffix starting with number',
        ];

        yield 'feature suffix with multiple dashes' => [
            '^2.12.0--my--feature--',
            '^2.12.0',
            'my--feature',
            Stability::Preview,
            'feature suffix with multiple dashes',
        ];

        yield 'hyphen range' => ['1.0 - 2.0@beta', '1.0 - 2.0', null, Stability::Beta, 'Hyphen range with a stability'];
        yield 'alternatives' => ['^1.2 || ^2.0', '^1.2 || ^2.0', null, Stability::Stable, 'Alternatives'];
        yield 'range' => ['>=1.0 <2.0', '>=1.0 <2.0', null, Stability::Stable, 'Range'];
        yield 'feature suffix of a single word' => ['^1.0.0-experimental', '^1.0.0', 'experimental', Stability::Preview, 'Feature'];
        yield 'feature suffix with a stability' => ['^2.12.0-hotfix@rc', '^2.12.0', 'hotfix', Stability::RC, 'Feature with a stability'];
        yield 'feature suffix of alternatives' => ['^1.2 || ^2.0-feature', '^1.2 || ^2.0', 'feature', Stability::Preview, 'Feature of alternatives'];
        yield 'numeric tail' => ['1.0.0-1', '1.0.0-1', null, Stability::Preview, 'Numeric tail'];
        yield 'pre-release lower bound of a range' => ['>=3.5.0-beta.1 <3.5.0', '>=3.5.0-beta.1 <3.5.0', null, Stability::Beta, 'Pre-release lower bound'];
        yield 'pre-release upper bound of a range' => ['>=1.0 <2.0-RC1', '>=1.0 <2.0-RC1', null, Stability::RC, 'Pre-release upper bound'];
        yield 'pre-release in an alternative' => ['^1.0 || ^2.0-beta.1', '^1.0 || ^2.0-beta.1', null, Stability::Beta, 'Pre-release alternative'];
        yield 'pre-release lower bound of a hyphen range' => ['1.0.0-beta.1 - 2.0.0', '1.0.0-beta.1 - 2.0.0', null, Stability::Beta, 'Pre-release hyphen range'];
        yield 'least stable of pre-release bounds' => ['>=1.0.0-alpha.1 <2.0.0-RC1', '>=1.0.0-alpha.1 <2.0.0-RC1', null, Stability::Alpha, 'Least stable bound'];
        yield 'bare stability keyword in a range' => ['>=1.0-beta <2.0', '>=1.0-beta <2.0', null, Stability::Beta, 'Bare keyword bound'];
        yield 'explicit stability over pre-release bounds' => ['>=3.5.0-beta.1 <3.5.0@alpha', '>=3.5.0-beta.1 <3.5.0', null, Stability::Alpha, 'Explicit stability'];
    }

    public static function provideInvalidConstraints(): \Generator
    {
        // Empty constraints
        yield 'empty string' => [
            '',
            'Version constraint cannot be empty',
            'Empty constraint string',
        ];

        yield 'whitespace only' => [
            '   ',
            'Version constraint cannot be empty',
            'Whitespace-only constraint string',
        ];

        // Invalid base version format
        yield 'invalid base version no numbers' => [
            'invalid',
            'Invalid base version format: invalid',
            'Base version without numbers',
        ];

        yield 'invalid base version starting with letter' => [
            'abc1.2.3',
            'Invalid base version format: abc1.2.3',
            'Base version starting with letters',
        ];

        yield 'feature suffix with special characters' => [
            '^2.12.0-feature@test',
            'Invalid stability level: @test',
            'Feature suffix with special characters',
        ];

        yield 'feature suffix with spaces' => [
            '^2.12.0-my feature',
            'Invalid feature suffix format: my feature.',
            'Feature suffix with spaces',
        ];

        // Invalid stability
        yield 'invalid explicit stability' => [
            '^2.12.0@invalid',
            'Invalid stability level: @invalid',
            'Invalid explicit stability',
        ];

        yield 'empty stability' => [
            '^2.12.0@',
            'Invalid stability level: @',
            'Empty explicit stability',
        ];

        // Multiple @ symbols
        yield 'multiple stability indicators' => [
            '^2.12.0@beta@alpha',
            'Invalid stability level: @beta@alpha',
            'Multiple @ symbols in constraint',
        ];

        yield 'base version of a feature build' => ['abc-feature', 'Invalid base version format: abc.', 'Base version of a feature build'];
        yield 'wildcard with an operator' => ['>=1.*', 'A wildcard version takes no operator', 'Wildcard with an operator'];
        yield 'reversed operator' => ['=>1.0', 'Unsupported operator `=>`', 'Reversed operator'];
        yield 'branch' => ['dev-master', 'Invalid base version format: dev.', 'Branch name'];
        yield 'empty alternative' => ['^1.0 ||', 'Empty alternative', 'Empty alternative'];
        yield 'empty term' => ['1.2,', 'Empty term in the version constraint `1.2,`.', 'Empty term'];
        yield 'feature suffix after a pre-release bound of a range' => ['>=3.5.0-beta.1 <3.5.0-custom', 'Invalid base version format: <3.5.0-custom.', 'Feature in a range'];
    }

    public static function provideComparableConstraints(): \Generator
    {
        yield ['^1.0-priority@dev', '1.3.1-priority.0', true];
        yield ['^1.0-priority', '1.3.1-priority.0', true];
        yield ['^1.0-priority@rc', '1.3.1-RC1-priority.0', true];
        yield ['^1.0-priority@RC', '1.3.1-RC1-priority.0', true];
        yield ['^1.0-priority@RC', '1.3.1-priority.0', false];

        // A numbered pre-release names one release
        yield 'exact pre-release with explicit stability' => ['3.5.0-beta.1@beta', 'v3.5.0-beta.1', true];
        yield 'exact pre-release with implicit stability' => ['3.5.0-beta.1', 'v3.5.0-beta.1', true];
        yield 'exact release candidate' => ['3.5.0-RC1', 'v3.5.0-RC1', true];
        yield 'exact dotted release candidate' => ['2025.1.0-rc.2@rc', 'v2025.1.0-rc.2', true];
        yield 'another spelling of the release' => ['3.5.0-rc.1', 'v3.5.0-RC1', true];
        yield 'beta abbreviation' => ['1.0.0-b2', 'v1.0.0-beta.2', true];
        yield 'exact pre' => ['2.0.0-pre.3', 'v2.0.0-pre3', true];
        yield 'exact preview' => ['2.0.0-preview5', 'v2.0.0-preview5', true];
        yield 'exact unstable' => ['2.0.0-unstable2', 'v2.0.0-unstable.2', true];
        yield 'exact snapshot' => ['2.0.0-snapshot1', 'v2.0.0-snapshot1', true];
        yield 'exact nightly' => ['1.0.0-nightly20250503', 'v1.0.0-nightly20250503', true];
        yield 'another nightly' => ['1.0.0-nightly20250503', 'v1.0.0-nightly20250504', false];
        yield 'another pre-release number' => ['3.5.0-beta.1@beta', 'v3.5.0-beta.2', false];
        yield 'another stability with the same number' => ['3.5.0-beta.1', 'v3.5.0-alpha.1', false];
        yield 'final release of a pre-release' => ['3.5.0-beta.1@beta', 'v3.5.0', false];
        yield 'another version number' => ['3.5.0-beta.1', 'v3.5.1-beta.1', false];
        yield 'pre-release below the minimum stability' => ['3.5.0-beta.1@stable', 'v3.5.0-beta.1', false];
        yield 'feature build of the version' => ['3.5.0-beta.1', 'v3.5.0-custom', false];
        yield 'underscore spelling in the tag' => ['1.0.0-alpha_2', 'v1.0.0-alpha_2', true];
        yield 'explicit stability looser than the pre-release' => ['3.5.0-RC1@beta', 'v3.5.0-RC1', true];
        yield 'explicit stability stricter than the pre-release' => ['3.5.0-beta.1@rc', 'v3.5.0-beta.1', false];
        yield 'feature build after the pre-release' => ['1.3.1-RC1', 'v1.3.1-RC1-priority.0', false];
        yield 'number after the pre-release' => ['=3.5.0-beta.1', 'v3.5.0-beta.1.2', false];
        yield 'platform after the pre-release' => ['3.5.0-RC1', 'v3.5.0-RC1-linux', false];
        yield 'explicit equality with a tail after the pre-release' => ['==3.5.0-RC1', 'v3.5.0-RC1-linux', false];

        // A pre-release bound of a range
        yield 'caret above the bound' => ['^3.5.0-beta.1@beta', 'v3.5.0-rc.1', true];
        yield 'caret on the final release' => ['^3.5.0-beta.1@beta', 'v3.5.0', true];
        yield 'caret up to the next major' => ['^3.5.0-beta.1@beta', 'v3.6.0', true];
        yield 'caret below the bound' => ['^3.5.0-beta.1@beta', 'v3.5.0-alpha.9', false];
        yield 'caret past the next major' => ['^3.5.0-beta.1@beta', 'v4.0.0', false];
        yield 'caret on an older version' => ['^3.5.0-beta.1@beta', 'v3.4.9', false];
        yield 'tilde on a later patch' => ['~3.5.0-rc.1', 'v3.5.4', true];
        yield 'tilde past the minor' => ['~3.5.0-rc.1', 'v3.6.0', false];
        yield 'at least the bound' => ['>=3.5.0-beta.2@beta', 'v3.5.0-beta.2', true];
        yield 'below at least the bound' => ['>=3.5.0-beta.2@beta', 'v3.5.0-beta.1', false];
        yield 'above the bound' => ['>3.5.0-beta.1@beta', 'v3.5.0-beta.2', true];
        yield 'equal to above the bound' => ['>3.5.0-beta.1@beta', 'v3.5.0-beta.1', false];
        yield 'below the bound' => ['<3.5.0-beta.2@beta', 'v3.5.0-beta.1', true];
        yield 'equal to below the bound' => ['<3.5.0-beta.2@beta', 'v3.5.0-beta.2', false];
        yield 'older version below the bound' => ['<3.5.0-beta.2@beta', 'v3.4.0', true];
        yield 'final release below its pre-release' => ['<3.5.0-beta.2@beta', 'v3.5.0', false];
        yield 'at most the bound' => ['<=3.5.0-beta.2@beta', 'v3.5.0-beta.2', true];
        yield 'explicit equality' => ['==3.5.0-rc.1', 'v3.5.0-RC1', true];
        yield 'nightly range' => ['>=1.0.0-nightly20250503@nightly', 'v1.0.0-nightly20250601', true];
        yield 'caret on a number after the pre-release' => ['^3.5.0-beta.1', 'v3.5.0-beta.1.2', true];
        yield 'at least the bound with a tail after it' => ['>=3.5.0-RC1', 'v3.5.0-RC1-linux', true];
        yield 'above the bound with a tail after it' => ['>3.5.0-RC1', 'v3.5.0-RC1-linux', true];
        yield 'above the bound with a number after it' => ['>3.5.0-RC1', 'v3.5.0-RC1.2', true];
        yield 'caret on the bound with a tail after it' => ['^3.5.0-RC1', 'v3.5.0-RC1-linux', true];
        yield 'tilde on the bound with a tail after it' => ['~3.5.0-RC1', 'v3.5.0-RC1-linux', true];
        yield 'below the bound with a tail after it' => ['<3.5.0-RC1', 'v3.5.0-RC1-linux', false];
        yield 'at most the bound with a tail after it' => ['<=3.5.0-RC1', 'v3.5.0-RC1-linux', false];
        yield 'below the next pre-release with a tail after the previous one' => ['<3.5.0-RC2', 'v3.5.0-RC1-linux', true];
        yield 'above the bound on a next version below the stability' => ['>3.5.0-beta.2', 'v3.6.0-alpha', false];
        yield 'above the bound on a next version' => ['>3.5.0-beta.2', 'v3.6.0', true];
        yield 'tilde from a pre-release on a later patch' => ['~3.5.0-beta.1', 'v3.5.4', true];
        yield 'tilde from a pre-release past the minor' => ['~3.5.0-beta.1', 'v3.6.0', false];

        // A pre-release in the constraint sets the minimum stability for an upper bound too
        yield 'below the bound on an earlier pre-release' => ['<3.5.0-beta.2', 'v3.5.0-beta.1', true];
        yield 'below the bound on a less stable pre-release' => ['<3.5.0-beta.2', 'v3.5.0-alpha.9', false];
        yield 'below the bound on an older dev build' => ['<3.5.0-beta.2', 'v3.4.0-dev', false];
        yield 'below the bound on an older release' => ['<3.5.0-beta.2', 'v3.4.0', true];
        yield 'below the bound on the bound' => ['<3.5.0-beta.2', 'v3.5.0-beta.2', false];
        yield 'below the bound with alpha on an alpha' => ['<3.5.0-beta.2@alpha', 'v3.5.0-alpha.9', true];
        yield 'below the bound with alpha on a dev build' => ['<3.5.0-beta.2@alpha', 'v3.4.0-dev', false];
        yield 'below the bound with dev on an earlier pre-release' => ['<3.5.0-beta.2@dev', 'v3.5.0-beta.1', true];
        yield 'below the bound with dev on an alpha' => ['<3.5.0-beta.2@dev', 'v3.5.0-alpha.9', true];
        yield 'below the bound with dev on a dev build' => ['<3.5.0-beta.2@dev', 'v3.4.0-dev', true];
        yield 'below the bound with dev on an older release' => ['<3.5.0-beta.2@dev', 'v3.4.0', true];
        yield 'below the bound with dev on the bound' => ['<3.5.0-beta.2@dev', 'v3.5.0-beta.2', false];

        // Versions with more than three number parts
        yield 'four parts exact' => ['1.2.3.4', 'v1.2.3.4', true];
        yield 'four parts exact pre-release' => ['1.2.3.4-beta.1', 'v1.2.3.4-beta.1', true];
        yield 'four parts on another fourth part' => ['1.2.3.4', 'v1.2.3.5', false];
        yield 'five parts on an exact four' => ['1.2.3.4', 'v1.2.3.4.5', false];
        yield 'five parts on an explicit equality' => ['==1.2.3.4', 'v1.2.3.4.5', false];
        yield 'five parts above four' => ['>1.2.3.4', 'v1.2.3.4.5', true];
        yield 'five parts at least four' => ['>=1.2.3.4', 'v1.2.3.4.5', true];
        yield 'five parts below four' => ['<1.2.3.4', 'v1.2.3.4.5', false];
        yield 'five parts at most four' => ['<=1.2.3.4', 'v1.2.3.4.5', false];
        yield 'five parts below the next fourth part' => ['<1.2.3.5', 'v1.2.3.4.5', true];
        yield 'five parts in a caret' => ['^1.2', 'v1.2.3.4.5', true];
        yield 'five parts in a tilde' => ['~1.2.3.4', 'v1.2.3.4.5', true];
        yield 'six parts above four' => ['>1.2.3.4', 'v1.2.3.4.5.6', true];
        yield 'trailing zero after four parts on an explicit equality' => ['==1.2.3.4', 'v1.2.3.4.0', true];
        yield 'trailing zeros after four parts' => ['1.2.3.4', 'v1.2.3.4.0.0', true];
        yield 'trailing zeros after three parts' => ['1.2.3', 'v1.2.3.0.0', true];
        yield 'trailing zero after four parts above four' => ['>1.2.3.4', 'v1.2.3.4.0', false];
        yield 'trailing zero after four parts below four' => ['<1.2.3.4', 'v1.2.3.4.0', false];
        yield 'zero part before a meaningful one' => ['1.2.3.4', 'v1.2.3.4.0.5', false];
        yield 'zero parts before a meaningful one' => ['1.0', 'v1.0.0.0.1', false];
        yield 'trailing zeros before a pre-release' => ['3.5.0-RC1', 'v3.5.0.0.0-RC1', true];
        yield 'five parts above a four parts pre-release' => ['^1.2.3.4-beta.1', 'v1.2.3.4.5', true];
        yield 'five parts on an exact four parts pre-release' => ['1.2.3.4-beta.1', 'v1.2.3.4.5', false];

        // A pre-release glued to the number
        yield 'exact release candidate without a separator in the tag' => ['2.0.0-rc1', 'v2.0.0rc1', true];
        yield 'exact beta abbreviation without a separator in the tag' => ['1.0.0-b2', '1.0.0b2', true];

        // A long major part is an ordinary number
        yield 'date major part with four parts' => ['^1.0', '20250101.1.2.3', false];
        yield 'date major part in a caret of its own' => ['^20250101.1', '20250101.1.2.3', true];
        yield 'six digit major part with four parts' => ['^1.0', '123456.1.2.3', false];
        yield 'six digit major part at least a bound' => ['>=1.0', '100000.0.0.1', true];
        yield 'six digit major part with five parts on a pre-release bound' => ['^1.0.0-beta.1', '123456.0.0.0.1', false];

        // A bare stability keyword still matches every pre-release of that stability
        yield 'bare stability keyword' => ['3.5.0-beta', 'v3.5.0-beta.3', true];

        // A numeric tail orders as a part of the number, in matching as in sorting
        yield 'numeric tail below a pre-release bound' => ['<3.5.0-preview.3', 'v3.5.0-1', false];
        yield 'numeric tail at least a pre-release bound' => ['>=3.5.0-preview.3', 'v3.5.0-1', true];
        yield 'numeric tail at most a preview' => ['<=1.0.0@preview', 'v1.0.0-1', false];
        yield 'numeric tail above its number' => ['>1.0.0@preview', 'v1.0.0-1', true];
        yield 'numeric tail on an exact number' => ['1.0-dev', 'v1.0.0-1', false];

        // Five and more number parts in the constraint
        yield 'five parts at least a five parts bound' => ['>=1.2.3.4.5', 'v1.2.3.4.5', true];
        yield 'four parts at least a five parts bound' => ['>=1.2.3.4.5', 'v1.2.3.4', false];
        yield 'five parts pre-release' => ['1.2.3.4.5-beta.1', 'v1.2.3.4.5-beta.1', true];
        yield 'caret on a five parts pre-release' => ['^1.2.3.4.5-beta.1', 'v1.2.3.4.6', true];

        // Composer syntax around the stability and the feature suffix
        yield 'range with a stability' => ['>=1.0 <2.0@beta', 'v1.5.0-beta.1', true];
        yield 'range with a stability on the upper bound' => ['>=1.0 <2.0@beta', 'v2.0.0-beta.1', false];
        yield 'alternatives with a stability' => ['^1.2 || ^2.0@beta', 'v2.1.0-beta', true];
        yield 'hyphen range' => ['1.0 - 2.0', 'v2.0.5', true];
        yield 'hyphen range past the upper bound' => ['1.0 - 2.0', 'v2.1', false];
        yield 'hyphen range with a stability' => ['1.0 - 2.0@beta', 'v1.5.0-beta', true];
        yield 'wildcard with a stability' => ['1.*@beta', 'v1.5.0-beta', true];
        yield 'match all' => ['*', 'v20250101.1.2.3', true];
        yield 'match all keeps the stability' => ['*', 'v1.0.0-beta', false];
        yield 'not equal' => ['!=1.2.3', 'v1.2.4', true];
        yield 'not equal on the version' => ['!=1.2.3@beta', 'v1.2.3-beta.1', false];
        yield 'not equal to a pre-release' => ['!=3.5.0-beta.1', 'v3.5.0-beta.2', true];
        yield 'prefixed version' => ['v1.2.3', 'v1.2.3', true];
        yield 'prefixed pre-release' => ['v3.5.0-beta.1', 'v3.5.0-beta.1', true];
        yield 'space after the operator' => ['>= 1.2', 'v1.3', true];
        yield 'space after the operator of a pre-release' => ['>= 3.5.0-beta.1', 'v3.5.0-beta.2', true];
        yield 'feature on a range' => ['^1.0-priority', 'v2.0.0-priority.0', false];
        yield 'exact number of a feature build' => ['1.3.1-priority', 'v1.3.1-priority.0', true];
        yield 'not equal in the Composer spelling' => ['<>1.2.3', 'v1.2.4', true];

        // A numeric tail is a part of the version number
        yield 'exact numeric tail' => ['1.0.0-1', 'v1.0.0-1', true];
        yield 'exact numeric tail on its number' => ['1.0.0-1', 'v1.0.0', false];
        yield 'exact numeric tail on another one' => ['1.0.0-1', 'v1.0.0-2', false];
        yield 'at least a numeric tail on a later one' => ['>=1.0.0-1', 'v1.0.0-2', true];
        yield 'caret on a numeric tail' => ['^1.0.0-1', 'v1.9.0', true];

        // Pre-release bounds of ranges set the minimum stability
        yield 'pre-release lower bound on a later pre-release' => ['>=3.5.0-beta.1 <3.5.0-RC1', 'v3.5.0-beta.2', true];
        yield 'pre-release lower bound on an earlier stability' => ['>=3.5.0-beta.1 <3.5.0-RC1', 'v3.5.0-alpha.1', false];
        yield 'pre-release upper bound with a looser explicit stability' => ['>=3.4.0 <3.5.0-RC1@alpha', 'v3.4.5-alpha', true];
        yield 'pre-release upper bound without an explicit stability' => ['>=3.4.0 <3.5.0-RC1', 'v3.4.5-alpha', false];
        yield 'pre-release bounds with a stricter explicit stability' => ['>=3.5.0-beta.1 <3.5.0-RC1@stable', 'v3.5.0-beta.2', false];
        yield 'upper bound without a pre-release below every build of its number' => ['>=3.5.0-beta.1 <3.5.0', 'v3.5.0-beta.2', false];
        yield 'pre-release alternative on its bound' => ['^1.0 || ^2.0-beta.1', 'v2.0.0-beta.1', true];
        yield 'pre-release alternative on another alternative' => ['^1.0 || ^2.0-beta.1', 'v1.5.0', true];
        yield 'pre-release upper bound on a release below it' => ['>=1.0 <2.0-RC1', 'v1.5.0', true];
        yield 'pre-release upper bound on its bound' => ['>=1.0 <2.0-RC1', 'v2.0.0-RC1', false];
        yield 'pre-release upper bound below the stability' => ['>=1.0 <2.0-RC1', 'v2.0.0-beta', false];
        yield 'hyphen range from a pre-release on its bound' => ['1.0.0-beta.1 - 2.0.0', 'v1.0.0-beta.1', true];
        yield 'hyphen range from a pre-release on an earlier stability' => ['1.0.0-beta.1 - 2.0.0', 'v1.0.0-alpha', false];
        yield 'hyphen range to a short pre-release on its bound' => ['1.0 - 2.0-beta.1', 'v2.0.0-beta.1', true];
        yield 'hyphen range to a short pre-release past its bound' => ['1.0 - 2.0-beta.1', 'v2.0.0-beta.2', false];
        yield 'least stable of pre-release bounds' => ['>=1.0.0-alpha.1 <2.0.0-RC1', 'v1.5.0-beta', true];

        // A feature suffix keeps filtering every alternative
        yield 'feature of alternatives' => ['^1.2 || ^2.0-feature', 'v2.1.0-feature', true];
        yield 'feature of alternatives on a release' => ['^1.2 || ^2.0-feature', 'v1.3.0', false];
        yield 'feature with a stability' => ['^2.12.0-hotfix@rc', 'v2.13.0-RC1-hotfix', true];
        yield 'feature with a stability on a feature build' => ['^2.12.0-hotfix@rc', 'v2.12.0-hotfix', false];
        yield 'single word feature' => ['^2.12.0-experimental', 'v2.12.0-experimental', true];
        yield 'single word feature on a release' => ['^2.12.0-experimental', 'v2.13.0', false];
    }

    public static function providePreReleases(): \Generator
    {
        yield 'dotted beta' => ['3.5.0-beta.1', '3.5.0', Stability::Beta, 1, Stability::Beta];
        yield 'release candidate' => ['3.5.0-RC1', '3.5.0', Stability::RC, 1, Stability::RC];
        yield 'beta abbreviation' => ['3.5.0-b2', '3.5.0', Stability::Beta, 2, Stability::Beta];
        yield 'nightly' => ['1.0.0-nightly20250503', '1.0.0', Stability::Nightly, 20250503, Stability::Nightly];
        yield 'explicit stability wins' => ['3.5.0-alpha-2@dev', '3.5.0', Stability::Alpha, 2, Stability::Dev];
        yield 'range' => ['^3.5.0-rc.2', '^3.5.0', Stability::RC, 2, Stability::RC];
        yield 'bare stability keyword' => ['3.5.0-beta', '3.5.0', null, null, Stability::Beta];
        yield 'prefixed version' => ['v3.5.0-beta.1', 'v3.5.0', Stability::Beta, 1, Stability::Beta];
        yield 'not equal' => ['!=3.5.0-beta.1', '!=3.5.0', Stability::Beta, 1, Stability::Beta];
        yield 'five number parts' => ['^1.2.3.4.5-rc.1', '^1.2.3.4.5', Stability::RC, 1, Stability::RC];
    }

    #[DataProvider('provideValidConstraints')]
    #[Test]
    public function fromConstraintStringWithValidInput(
        string $constraint,
        string $expectedBaseVersion,
        ?string $expectedFeatureSuffix,
        Stability $expectedStability,
        string $description,
    ): void {
        $result = Constraint::fromConstraintString($constraint);

        Assert::same($result->versionConstraint, $expectedBaseVersion, "Base version for: {$description}");
        Assert::same($result->featureSuffix, $expectedFeatureSuffix, "Feature suffix for: {$description}");
        Assert::same($result->minimumStability, $expectedStability, "Stability for: {$description}");
    }

    #[DataProvider('provideInvalidConstraints')]
    #[Test]
    public function fromConstraintStringWithInvalidInput(
        string $constraint,
        string $expectedExceptionMessage,
        string $description,
    ): void {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining($expectedExceptionMessage);

        Constraint::fromConstraintString($constraint);
    }

    #[Test]
    public function getBaseConstraint(): void
    {
        $constraint = Constraint::fromConstraintString('^2.12.0', 'feature', Stability::Beta);

        Assert::same($constraint->versionConstraint, '^2.12.0');
    }

    #[Test]
    public function toStringWithBaseVersionOnly(): void
    {
        $constraint = Constraint::fromConstraintString('^2.12.0', null, Stability::Stable);

        $result = (string) $constraint;

        Assert::same($result, '^2.12.0');
    }

    #[Test]
    public function toStringWithFeatureSuffixAndCustomStability(): void
    {
        $constraint = Constraint::fromConstraintString('^2.12.0-feature@beta');

        $result = (string) $constraint;

        Assert::same($result, '^2.12.0-feature@beta');
    }

    #[DataProvider('providePreReleases')]
    #[Test]
    public function numberedPreReleaseIsAPartOfTheVersion(
        string $constraint,
        string $expectedBaseVersion,
        ?Stability $expectedPreReleaseStability,
        ?int $expectedPreReleaseNumber,
        Stability $expectedStability,
    ): void {
        $result = Constraint::fromConstraintString($constraint);

        Assert::same($result->versionConstraint, $expectedBaseVersion);
        Assert::same($result->preRelease?->stability, $expectedPreReleaseStability);
        Assert::same($result->preRelease?->number, $expectedPreReleaseNumber);
        Assert::same($result->minimumStability, $expectedStability);
        Assert::null($result->featureSuffix);
    }

    #[Test]
    public function rejectsAnUnknownOperatorWithAPreRelease(): never
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Unsupported operator `=>`');

        Constraint::fromConstraintString('=>3.5.0-beta.1');
    }

    #[Test]
    public function toStringKeepsThePreRelease(): void
    {
        Assert::same((string) Constraint::fromConstraintString('^3.5.0-beta.1@beta'), '^3.5.0-beta.1@beta');
    }

    #[DataProvider('provideComparableConstraints')]
    #[Test]
    public function isSatisfiedBy(string $constraint, string $version, bool $expected): void
    {
        $constraintObj = Constraint::fromConstraintString($constraint);
        $versionObj = Version::fromVersionString($version);

        $result = $constraintObj->isSatisfiedBy($versionObj);

        Assert::same($result, $expected, "Constraint: {$constraint}, Version: {$version}");
    }
}
