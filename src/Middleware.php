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

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Interface Middleware.
 *
 * Middleware is code that exists between the request and response,
 * which can take the incoming request, perform actions based on it,
 * and either complete the response or pass delegation on to the next middleware in the queue.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 * @version 0.1
 */
interface Middleware
{
    /**
     * Process an incoming request and/or response.
     *
     * Accepts a request and a response instance, and does something with them.
     *
     * If the response is not complete and/or further processing would not
     * interfere with the work done in the middleware, or if the middleware
     * wants to delegate to another process, it can use the `$next` callable
     * if present.
     *
     * If the middleware does not return a value, execution of the current
     * request is considered complete, and the response instance provided will
     * be considered the response to return.
     *
     * Alternately, the middleware may return a response instance.
     *
     * Often, middleware will `return $next();`, with the assumption that a
     * later middleware will return a response.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     *
     * @return Response|null
     */
    public function __invoke(Request $request, Response $response, callable $next);
}
