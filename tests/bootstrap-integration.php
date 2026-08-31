<?php

declare(strict_types=1);

/**
 * Bootstrap for the integration suite.
 *
 * Unlike the unit suite these tests need a running shop: the module has to be installed and
 * activated so that the *_parent chain exists, and the database has to hold the module table.
 * PHPUnit has already loaded the module's own autoloader by the time this file runs, so all
 * that is left is to pull in the shop.
 *
 * The path differs per environment, hence the environment variable. The default matches the
 * Docker test shops in this repository.
 */
$shopBootstrap = getenv('OXID_SHOP_BOOTSTRAP') ?: '/var/www/html/source/bootstrap.php';

if (!is_file($shopBootstrap)) {
    fwrite(
        STDERR,
        "Shop bootstrap not found at: $shopBootstrap\n" .
        "Set OXID_SHOP_BOOTSTRAP to the shop's source/bootstrap.php.\n"
    );
    exit(1);
}

/**
 * Load the shop, then give the module's autoloader precedence again - but only for the
 * packages the test run itself owns.
 *
 * The shop prepends its own autoloader during bootstrap. Left alone it resolves PHPUnit from
 * the shop's vendor (8.5, pulled in by oxid-esales/testing-library on the OXID 6 line) instead
 * of the module's 9.6, which fails with "Undefined class constant 'COLOR_AUTO'".
 *
 * Handing the module's autoloader everything is wrong though: it also carries psr/log,
 * guzzle and symfony components, and those must stay on the shop's versions - that is what the
 * module runs against in production. OXID 6 implements the psr/log 1.x LoggerInterface, so
 * forcing the module's psr/log 3.x on it is a fatal error on PHP 8.
 */
$moduleAutoloader = require __DIR__ . '/../vendor/autoload.php';

require_once $shopBootstrap;

// Take the module's autoloader out of the chain completely. PHPUnit's binary registered it
// before the shop's, so it wins by registration order alone - and it carries psr/log, guzzle
// and symfony components. Whichever of those wins has to be the shop's copy, because that is
// what the module runs against in production. Both OXID lines break otherwise, in opposite
// directions: OXID 6's LoggerWrapper and the monolog shipped with OXID 7 both implement the
// psr/log 1.x interface, and the module resolves psr/log 3.x on PHP 8.
$moduleAutoloader->unregister();

$testOnlyPrefixes = [
    'foun10\\DeepL\\Tests\\',
    'PHPUnit\\',
    'SebastianBergmann\\',
    'PharIo\\',
    'DeepCopy\\',
    'Prophecy\\',
    'TheSeer\\',
    'PhpParser\\',
    'Doctrine\\Instantiator\\',
];

// Put it back only for the packages the test run itself owns. The module's own classes are
// not in this list on purpose: the shop resolves them through the path repository, which is
// exactly the code path production uses.
spl_autoload_register(
    static function (string $class) use ($moduleAutoloader, $testOnlyPrefixes): void {
        foreach ($testOnlyPrefixes as $prefix) {
            if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                $moduleAutoloader->loadClass($class);

                return;
            }
        }
    },
    true,
    true
);
