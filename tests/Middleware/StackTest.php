<?php
/**
 * This file is part of the HTTP Client package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://rebilly.com
 */

namespace Rebilly\HttpClient\Middleware;

use Rebilly\HttpClient\TestCase;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

/**
 * Class StackTest.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 */
class StackTest extends TestCase
{
    /**
     * @test
     */
    public function useMiddlewareStack()
    {
        $middleware = new Stack([
            function (Request $request, Response $response, $next) {
                return call_user_func($next, $request, $response->withHeader('X-Header1', 'dummy'));
            },
            function (Request $request, Response $response, $next) {
                return call_user_func($next, $request, $response->withHeader('X-Header2', 'dummy'));
            },
            function (Request $request, Response $response, $next) {
                call_user_func($next, $request, $response->withHeader('X-Header3', 'dummy'));
                return null;
            },
            function (Request $request, Response $response, $next) {
                return call_user_func($next, $request, $response->withHeader('X-Header4', 'dummy'));
            }
        ]);

        $done = function (Request $request, Response $response) {
            unset($request);
            return $response;
        };

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

        /** @var Response $result*/
        $result = call_user_func($middleware, $request, $response, $done);

        $this->assertEquals('dummy', $result->getHeaderLine('X-Header1'));
        $this->assertEquals('dummy', $result->getHeaderLine('X-Header2'));

        // The 3rd middleware returns null, execution is considered complete.
        $this->assertFalse($result->hasHeader('X-Header3'));
        $this->assertFalse($result->hasHeader('X-Header4'));
    }
}
