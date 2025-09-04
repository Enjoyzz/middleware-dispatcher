# HTTP Middleware Dispatcher

A PSR-15 compliant middleware dispatcher that provides flexible middleware queue management with support for various middleware formats.

## Requirements
- PHP 8.1 or higher
- PSR-15 implementation
- PSR-7/PSR-17 implementations for HTTP messages

## Installation

```bash
composer require enjoys/middleware-dispatcher
```

## Usage
## Basic Usage

```php
use Enjoys\MiddlewareDispatcher\HttpMiddlewareDispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

// Create your final request handler
$finalHandler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Return your final response
        return new Response();
    }
};

// Create the dispatcher
$dispatcher = new HttpMiddlewareDispatcher($finalHandler);

// Set up middleware queue
$middlewares = [
    new MyFirstMiddleware(),
    new MySecondMiddleware(),
    function ($request, $handler) {
        // Callable middleware
        return $handler->handle($request);
    }
];

$dispatcher->setQueue($middlewares);

// Handle the request
$response = $dispatcher->handle($request);

```

### With Middleware Resolver

```php
use Enjoys\MiddlewareDispatcher\MiddlewareResolverInterface;
use Psr\Http\Server\MiddlewareInterface;

class MyMiddlewareResolver implements MiddlewareResolverInterface
{
    public function resolve(mixed $entry): ?MiddlewareInterface
    {
        if (is_string($entry) && class_exists($entry)) {
            return new $entry();
        }
        
        if (is_array($entry) && count($entry) === 2) {
            return new ClassMethodMiddleware($entry[0], $entry[1]);
        }
        
        return $entry;
    }
}

$resolver = new MyMiddlewareResolver();
$dispatcher = new HttpMiddlewareDispatcher($finalHandler, $resolver);
```

### Example PSR-11 Container Middleware Resolver 

```php

use Enjoys\MiddlewareDispatcher\MiddlewareResolverInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class Psr11ContainerMiddlewareResolver implements MiddlewareResolverInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function resolve(mixed $entry): null|MiddlewareInterface|callable
    {
        if ($entry instanceof MiddlewareInterface || is_callable($entry)) {
            return $entry;
        }

        if (is_string($entry)) {
            try {
                $entry = $this->container->get($entry);
                if ($entry instanceof MiddlewareInterface) {
                    return $entry;
                }
            } catch (ContainerExceptionInterface) {
                return null;
            }
        }
        return null;
    }
}
```

### Adding Middleware Dynamically
```php
// Add middleware to the current position in the queue
$dispatcher->addQueue([
    new AdditionalMiddleware(),
    function ($request, $handler) {
        // Another callable middleware
        return $handler->handle($request);
    }
]);
```

## API Reference
### Constructor
```php
public function __construct(
    RequestHandlerInterface $requestHandler,
    ?MiddlewareResolverInterface $resolver = null
)
```

### Methods
`setQueue(ArrayIterator|array $queue): void`
Sets the middleware queue. Throws InvalidArgumentException if queue is empty.

`handle(ServerRequestInterface $request): ResponseInterface`
Processes the request through the middleware queue.

`addQueue(array $middlewares): void`
Adds middleware(s) at the current position in the queue.

### Middleware Formats
The dispatcher supports multiple middleware formats:

**MiddlewareInterface instances**: Objects implementing Psr\Http\Server\MiddlewareInterface

**Callables**: Functions or closures with signature function(ServerRequestInterface, RequestHandlerInterface): ResponseInterface

**Resolvable entries**: Any format that can be resolved by your MiddlewareResolverInterface

### Error Handling
- Throws `InvalidArgumentException` when setting an empty queue
- Throws `RuntimeException` when encountering invalid middleware entries

