<?php

/**
 * Top-level bootstrap for Codeception. Loads HumHub's core test bootstrap so
 * Yii is initialized and module classes resolve through the autoloader the
 * same way they do at runtime. Matches the layout used by other HumHub
 * modules (see e.g. modules/email-whitelist).
 */

$testRoot = dirname(__DIR__);

\Codeception\Configuration::append(['test_root' => $testRoot]);
codecept_debug('Module root: ' . $testRoot);

$humhubPath = getenv('HUMHUB_PATH');
if ($humhubPath === false) {
    // No env override → assume the module sits under protected/modules/<id>
    // and the HumHub core lives five levels up.
    $humhubPath = dirname(__DIR__, 5);
}

\Codeception\Configuration::append(['humhub_root' => $humhubPath]);
codecept_debug('HumHub Root: ' . $humhubPath);

$globalConfig = require $humhubPath . '/protected/humhub/tests/codeception/_loadConfig.php';
require $globalConfig['humhub_root'] . '/protected/humhub/tests/codeception/_bootstrap.php';
