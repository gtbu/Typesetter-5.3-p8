<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Util;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener as TestListenerInterface;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Framework\WarningTestCase;

/**
 * @author Ion Bazan <ion.bazan@gmail.com>
 */
class TestListenerForV7 extends TestSuite implements TestListenerInterface
{
    private $trait;

    public function __construct(?TestSuite $suite = null)
    {
        if ($suite) {
            $this->setName($suite->getName().' with polyfills enabled');
            $this->addTest($suite);
        }
        $this->trait = new TestListenerTrait();
    }

    public function startTestSuite(TestSuite $suite): void
    {
        if (null === TestListenerTrait::$enabledPolyfills) {
            TestListenerTrait::$enabledPolyfills = false;
            $this->trait->startTestSuite($suite);
        }
        if ($suite instanceof TestListener) {
            TestListenerTrait::$enabledPolyfills = $suite->getName();
        }
    }

    public function addError(Test $test, \Throwable $t, float $time): void
    {
        $this->trait->addError($test, $t, $time);
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->trait->addError($test, $e, $time);
    }

    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
    }

    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
    }

    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
    }

    public function endTestSuite(TestSuite $suite): void
    {
        TestListenerTrait::$enabledPolyfills = false;
    }

    public function startTest(Test $test): void
    {
    }

    public function endTest(Test $test, float $time): void
    {
    }

    public static function warning($message): WarningTestCase
    {
        return new WarningTestCase($message);
    }
}
