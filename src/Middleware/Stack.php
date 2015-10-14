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

use SplDoublyLinkedList;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Rebilly\HttpClient\Middleware;

/**
 * Class Stack.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 * @version 0.1
 */
class Stack implements Middleware
{
    /**
     * @var SplDoublyLinkedList The FIFO stack
     */
    private $stack;

    /**
     * @var callable
     */
    private $dispatcher;

    /**
     * @var callable
     */
    private $done;

    /**
     * Constructor.
     *
     * @param callable[] $items
     */
    public function __construct(array $items = [])
    {
        $this->stack = new SplDoublyLinkedList();
        $this->stack->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);

        foreach (array_filter($items, 'is_callable') as $middleware) {
            $this->stack->push($middleware);
        }

        // The queue dispatcher, middleware which iterate queue
        $this->dispatcher = function (Request $request, Response $response) {
            // No middleware remains. Done.
            if (!$this->stack->valid()) {
                return call_user_func($this->done, $request, $response) ?: $response;
            }

            $layer = $this->stack->current();
            $this->stack->next();

            return call_user_func($layer, $request, $response, $this->dispatcher) ?: $response;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        // Rewind queue for each sending
        $this->stack->rewind();

        $this->done = $next;

        // Dispatch queue
        return call_user_func($this->dispatcher, $request, $response) ?: $response;
    }
}
