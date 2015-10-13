<?php
/**
 * This file is part of the HTTP Client package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://rebilly.com
 */

namespace Rebilly\HttpClient;

use PHPUnit_Framework_TestCase as BaseTestCase;

/**
 * Class TestCase.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $annotations = $this->getAnnotations();

        if (isset($annotations['class']['todo'])) {
            $this->markTestSkipped('Pending test case...');
        } elseif (isset($annotations['method']['todo'])) {
            $this->markTestSkipped('Pending test...');
        }
    }
}
