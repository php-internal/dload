<?php

declare(strict_types=1);

arch('Forgotten functions')
    ->expect(['dd', 'exit', 'die', 'var_dump', 'echo', 'print', 'trap', 'td', 'tr', 'error_log'])
    ->not
    ->toBeUsed();
