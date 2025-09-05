<?php

declare(strict_types=1);

namespace Enjoys\MiddlewareDispatcher;

use ArrayIterator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class HttpMiddlewareDispatcher implements RequestHandlerInterface
{

    private ArrayIterator $queue;

    /**
     * @param RequestHandlerInterface $requestHandler
     * @param MiddlewareResolverInterface|null $resolver
     */
    public function __construct(
        private readonly RequestHandlerInterface $requestHandler,
        private readonly ?MiddlewareResolverInterface $resolver = null
    ) {
        $this->queue = new ArrayIterator();
    }

    public function setQueue(ArrayIterator|array $queue): void
    {
        if (is_array($queue)) {
            $queue = new ArrayIterator($queue);
        }

        if ($queue->count() === 0) {
            throw new InvalidArgumentException('$queue cannot be empty');
        }

        /** @var ArrayIterator $queue */
        $this->queue = $queue;
        $this->queue->seek(0);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->queue->valid()) {
            return $this->requestHandler->handle($request);
        }

        $entry = $this->queue->current();

        if ($this->resolver !== null) {
            $entry = $this->resolver->resolve($entry);
        }


        $this->queue->next();

        if ($entry === null) {
            return $this->handle($request);
        }

        if ($entry instanceof MiddlewareInterface) {
            return $entry->process($request, $this);
        }

        if (is_callable($entry)) {
            /** @var callable(ServerRequestInterface, RequestHandlerInterface):ResponseInterface $entry */
            return $entry($request, $this);
        }


        throw new RuntimeException(
            sprintf(
                'Invalid middleware queue entry: %s. Middleware must either be callable or implement %s.',
                get_debug_type($entry),
                MiddlewareInterface::class
            )
        );
    }

    public function addQueue(array $middlewares): void
    {
        $key = $this->queue->key();
        if ($key !== null) {
            $queue = $this->queue->getArrayCopy();
            // array_reverse нужен для того, чтобы вставить массив как есть, так как
            // $key всё время одинаковый, иначе он вставится перевернутым
            foreach (array_reverse($middlewares) as $middleware) {
                $queue = array_insert_before($queue, $key, $middleware);
            }
            $this->queue = new ArrayIterator($queue);
            $this->queue->seek($key);
            return;
        }

        foreach ($middlewares as $middleware) {
            $this->queue->append($middleware);
        }
    }

}
