<?php

declare(strict_types=1);

$appPath = getenv('CRAFT_TEST_APP_PATH') ?: '/tmp/deb-craft-demo';
if (!is_file($appPath . '/bootstrap.php')) {
    throw new RuntimeException('Set CRAFT_TEST_APP_PATH to a disposable installed Craft 5 application.');
}

require $appPath . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
Craft::$app->getPlugins()->loadPlugins();
