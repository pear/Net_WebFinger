<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Net_WebFinger_AllTests::main');
}

require_once 'PHPUnit/Autoload.php';

class Net_WebFinger_AllTests
{
    public static function main()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Net_WebFinger tests');
        /** Add testsuites, if there is. */
        $suite->addTestFiles(
            glob(__DIR__ . '/Net/WebFinger{,/,/*/}*Test.php', GLOB_BRACE)
        );

        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Net_WebFinger_AllTests::main') {
    Net_WebFinger_AllTests::main();
}
?>