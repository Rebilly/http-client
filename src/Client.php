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
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Rebilly\HttpClient\Adapter\Curl\CurlAdapter;
use RuntimeException;

/**
 * Class Client.
 *
 * Magic facades for HTTP methods:
 *
 * @see Client::__call()
 * @see Client::send()
 *
 * @method Response get($uri, $headers = [], $options = [])
 * @method Response head($uri, $headers = [], $options = [])
 * @method Response post($payload, $uri, $headers = [], $options = [])
 * @method Response put($payload, $uri, $headers = [], $options = [])
 * @method Response delete($uri, $headers = [], $options = [])
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 * @version 0.1
 */
final class Client
{
    /**
     * @var Middleware
     */
    private $middleware;

    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @var array
     */
    private $requestOptions = [];

    /**
     * @var Request
     */
    private $lastRequest;

    /**
     * @var Response
     */
    private $lastResponse;

    /**
     * @param Adapter|callable|null $adapter
     * @param Middleware[]|callable[] $middleware
     */
    public function __construct($adapter = null, array $middleware = [])
    {
        $this->middleware = empty($middleware) ? $this : new Middleware\Stack($middleware);
        $this->adapter = $adapter ?: new CurlAdapter();
    }

    /**
     * Application (Client) should be a final layer in middleware stack.
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response)
    {
        // Send response to the adapter as a prototype
        $response = call_user_func($this->adapter, $request, $response, $this->requestOptions) ?: $response;

        if (!($response instanceof Response)) {
            throw new RuntimeException('Wrong response');
        }

        return $response;
    }

    /**
     * Magic methods support.
     *
     * @see Client::send()
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws RuntimeException
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        switch (strtoupper($name)) {
            case 'HEAD':
            case 'GET':
            case 'DELETE':
                array_unshift($arguments, null);
                array_unshift($arguments, $name);
                return call_user_func_array([$this, 'send'], $arguments);
            case 'POST':
            case 'PUT':
                array_unshift($arguments, $name);
                return call_user_func_array([$this, 'send'], $arguments);
        }

        throw new BadMethodCallException(sprintf('Call unknown method %s::%s', __CLASS__, $name));
    }

    /**
     * Send request.
     *
     * @param string $method The request method.
     * @param mixed $payload The request body.
     * @param string $uri The URL path or URL Template
     * @param array $headers The headers specific for request.
     * @param array $options The request options.
     *
     * @return Response
     */
    public function send($method, $payload, $uri, array $headers = [], array $options = [])
    {
        $this->requestOptions = $options;
        $this->lastRequest = $this->createRequest($method, $uri, $payload, $headers);
        $this->lastResponse = $this->createResponse();
        $this->lastResponse = call_user_func($this->middleware, $this->lastRequest, $this->lastResponse, $this);

        return $this->lastResponse;
    }

    /**
     * @return Request
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Factory method to create a new Request object.
     *
     * @param string $method
     * @param mixed $uri
     * @param mixed $payload
     * @param array $headers
     *
     * @return Request
     */
    public function createRequest($method, $uri, $payload, array $headers = [])
    {
        return new GuzzleRequest($method, $uri, $headers, $payload);
    }

    /**
     * Factory method to create a new Response object.
     *
     * @param int $status
     * @param array $headers
     * @param mixed $payload
     * @param string|null $reason
     *
     * @return Response
     */
    public function createResponse($status = 200, array $headers = [], $payload = null, $reason = null)
    {
        return new GuzzleResponse($status, $headers, $payload, '1.1', $reason);
    }
}
