<?php

arch('it should not use any debugging functions')
    ->expect([
        'dd',
        'debug_backtrace',
        'die',
        'dump',
        'echo',
        'eval',
        'exec',
        'exit',
        'passthru',
        'phpinfo',
        'print_r',
        'proc_open',
        'ray',
        'shell_exec',
        'system',
        'var_dump',
    ])
    ->each->not->toBeUsed();