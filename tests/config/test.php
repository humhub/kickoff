<?php

/**
 * Module-side test bootstrap config consumed by HumHub's
 * `protected/humhub/tests/codeception/_loadConfig.php`. Lists the modules the
 * test runner has to enable before running our suites and the fixtures it
 * should seed. Only the kickoff module is needed; the rest of the assertions
 * are pure-PHP and don't touch the DB.
 */
return [
    'modules' => ['kickoff'],
    'fixtures' => ['default'],
];
