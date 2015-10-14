<?php
/**
 * This file is part of the HTTP Client package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://rebilly.com
 */

namespace Rebilly\HttpClient\Adapter\Curl;

use Rebilly\HttpClient\TestCase;
use ReflectionMethod;
use RuntimeException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class CurlAdapterTest.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 */
class CurlAdapterTest extends TestCase
{
    /**
     * @test
     */
    public function sessionFactory()
    {
        $handler = new CurlAdapter();
        $ref = new ReflectionMethod(CurlAdapter::class, 'createSession');
        $ref->setAccessible(true);
        $session = $ref->invoke($handler);
        $this->assertInstanceOf(CurlSession::class, $session);
    }

    /**
     * @test
     * @dataProvider provideRequests
     *
     * @param Request $request
     * @param Response $response
     */
    public function sendRequest(Request $request, Response $response)
    {
        $fakeBody = json_encode([], JSON_FORCE_OBJECT);
        $fakeHeaders = "HTTP/1.1 200 OK\r\nContent-Type: application/json; charset=utf-8\r\n";

        /** @var CurlSession|MockObject $session */
        $session = $this->getMock(CurlSession::class);
        $session->method('execute')->will($this->returnValue($fakeHeaders . $fakeBody));
        $session->method('getInfo')->will($this->returnValue(strlen($fakeHeaders)));

        /** @var CurlAdapter|MockObject $handler */
        $handler = $this->getMock(CurlAdapter::class, ['createSession']);
        $handler
            ->method('createSession')
            ->will($this->returnValue($session));

        /** @var Response $response */
        $response = call_user_func($handler, $request, $response);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function invalidRequest()
    {
        $request = $this
            ->getMockBuilder(GuzzleRequest::class)
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this
            ->getMockBuilder(GuzzleResponse::class)
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CurlSession|MockObject $session */
        $session = $this->getMock(CurlSession::class, ['open']);
        $session->expects($this->any())->method('open')->will($this->returnValue(false));

        /** @var CurlAdapter|MockObject $handler */
        $handler = $this->getMock(CurlAdapter::class, ['createSession']);
        $handler->expects($this->any())->method('createSession')->will(
            $this->returnValue($session)
        );

        try {
            call_user_func($handler, $request, $response);
        } catch (RuntimeException $e) {
        } finally {
            if (!isset($e)) {
                $this->fail(
                    'Failed asserting that exception of type "RuntimeException" is thrown.'
                );
            }
        }

        /** @var CurlSession|MockObject $session */
        $session = $this->getMock(
            CurlSession::class,
            ['open', 'execute', 'setOptions', 'getErrorMessage', 'getErrorCode']
        );
        $session->expects($this->any())->method('open')->will($this->returnValue(true));
        $session->expects($this->any())->method('execute')->will($this->returnValue(false));

        /** @var CurlAdapter|MockObject $handler */
        $handler = $this->getMock(CurlAdapter::class, ['createSession']);
        $handler->expects($this->any())->method('createSession')->will(
            $this->returnValue($session)
        );

        try {
            call_user_func($handler, $request, $response);
        } catch (RuntimeException $e) {
        } finally {
            if (!isset($e)) {
                $this->fail(
                    'Failed asserting that exception of type "RuntimeException" is thrown.'
                );
            }
        }
    }

    /**
     * @return array
     */
    public function provideRequests()
    {
        foreach (['OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $request = $this
                ->getMockBuilder(GuzzleRequest::class)
                ->setMethods(['getMethod', 'getHeaders'])
                ->disableOriginalConstructor()
                ->getMock();

            $response = $this
                ->getMockBuilder(GuzzleResponse::class)
                ->setMethods(null)
                ->disableOriginalConstructor()
                ->getMock();

            $request->method('getMethod')->will($this->returnValue($method));
            $request->method('getHeaders')->will($this->returnValue(['X-Header' => ['Value']]));

            yield $method => [$request, $response];
        }
    }
}
