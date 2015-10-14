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

use BadMethodCallException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

/**
 * Class ClientTest.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 */
class ClientTest extends TestCase
{
    /**
     * @test
     */
    public function useInvalidAdapter()
    {
        $this->setExpectedException(RuntimeException::class);

        $handler = $this->getMockForAbstractClass(Adapter::class);
        $handler->expects($this->any())->method('__invoke')->willReturn('response');

        $client = new Client($handler);
        $client->get('http://localhost');
    }

    /**
     * @test
     * @dataProvider provideHttpMethods
     *
     * @param string $method
     */
    public function sendRequest($method)
    {
        $handler = $this->getMockForAbstractClass(Adapter::class);
        $client = new Client($handler);

        $response = $client->send($method, [], 'http://localhost');
        $this->assertInstanceOf(Response::class, $response);

        $this->assertInstanceOf(Request::class, $client->getLastRequest());
        $this->assertInstanceOf(Response::class, $client->getLastResponse());
    }

    /**
     * @test
     */
    public function sendRequestUsingFacades()
    {
        $handler = $this->getMockForAbstractClass(Adapter::class);

        $client = new Client($handler);

        $response = $client->get('http://localhost');
        $this->assertInstanceOf(Response::class, $response);

        $response = $client->post([], 'http://localhost');
        $this->assertInstanceOf(Response::class, $response);

        $response = $client->put([], 'http://localhost');
        $this->assertInstanceOf(Response::class, $response);

        $response = $client->delete('http://localhost');
        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * @test
     */
    public function callUnknownFacade()
    {
        $this->setExpectedException(BadMethodCallException::class);

        /** @var mixed $client */
        $client = new Client();
        $client->show('http://localhost');
    }

    /**
     * @todo
     * @test
     * @dataProvider provideHttpExceptionCodes
     *
     * @param int $code
     */
    public function sendClientExceptions($code)
    {
    }

    /**
     * @return array
     */
    public function provideHttpExceptionCodes()
    {
        return [
            [404],
            [410],
            [422],
            [400],
            [500],
        ];
    }

    /**
     * @return array
     */
    public function provideHttpMethods()
    {
        return [
            ['OPTIONS'],
            ['HEAD'],
            ['GET'],
            ['POST'],
            ['PUT'],
            ['DELETE'],
        ];
    }
}
