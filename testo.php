<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Mockery\MockeryPlugin;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/Unit'],
        ),
        new SuiteConfig(
            name: 'Integration',
            location: ['tests/Integration'],
        ),
        new SuiteConfig(
            name: 'Acceptance',
            location: ['tests/Acceptance'],
        ),
    ],
    # Verifies mock expectations and clears the Mockery container after every test.
    plugins: ApplicationPlugins::with(new MockeryPlugin()),
);
