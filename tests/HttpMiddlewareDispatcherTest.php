<?php

declare(strict_types=1);

namespace Tests\Enjoys\MiddlewareDispatcher;

use ArrayIterator;
use Enjoys\MiddlewareDispatcher\HttpMiddlewareDispatcher;
use Enjoys\MiddlewareDispatcher\MiddlewareResolverInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use RuntimeException;

class HttpMiddlewareDispatcherTest extends TestCase
{
    private ServerRequestInterface $request;
    private ResponseInterface $response;
    private RequestHandlerInterface $requestHandler;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
    }

    private function getQueueProperty(HttpMiddlewareDispatcher $dispatcher): ArrayIterator
    {
        $reflection = new ReflectionClass($dispatcher);
        $property = $reflection->getProperty('queue');
        return $property->getValue($dispatcher);
    }

    public function testConstructorInitializesEmptyQueue(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $queue = $this->getQueueProperty($dispatcher);

        $this->assertInstanceOf(HttpMiddlewareDispatcher::class, $dispatcher);
        $this->assertInstanceOf(ArrayIterator::class, $queue);
        $this->assertCount(0, $queue);
    }

    public function testSetQueueWithArray(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $middleware = $this->createMock(MiddlewareInterface::class);

        $dispatcher->setQueue([$middleware]);
        $queue = $this->getQueueProperty($dispatcher);

        $this->assertCount(1, $queue);
        $this->assertSame($middleware, $queue->current());
    }

    public function testSetQueueWithArrayIterator(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $iterator = new ArrayIterator([$middleware1, $middleware2]);
        $iterator->next();

        $dispatcher->setQueue($iterator);
        $queue = $this->getQueueProperty($dispatcher);

        $this->assertCount(2, $queue);
        $this->assertSame($middleware1, $queue->current());
    }

    public function testSetQueueWithEmptyArrayThrowsException(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$queue cannot be empty');

        $dispatcher->setQueue([]);
    }

    public function testSetQueueWithEmptyArrayIteratorThrowsException(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$queue cannot be empty');

        $dispatcher->setQueue(new ArrayIterator([]));
    }

    public function testHandleWithEmptyQueueCallsRequestHandler(): void
    {
        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithMiddlewareInterface(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(HttpMiddlewareDispatcher::class))
            ->willReturn($this->response);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware]);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithCallable(): void
    {
        $callable = function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
            return $this->response;
        };

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$callable]);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithNullAfterResolver(): void
    {
        $resolver = $this->createMock(MiddlewareResolverInterface::class);
        $resolver->method('resolve')
            ->willReturn(null);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler, $resolver);
        $dispatcher->setQueue(['some_middleware']);

        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithResolver(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willReturn($this->response);

        $resolver = $this->createMock(MiddlewareResolverInterface::class);
        $resolver->method('resolve')
            ->with('middleware_string')
            ->willReturn($middleware);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler, $resolver);
        $dispatcher->setQueue(['middleware_string']);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testHandleWithInvalidMiddlewareThrowsException(): void
    {
        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([new \stdClass()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid middleware queue entry:.*stdClass.*/');

        $dispatcher->handle($this->request);
    }

    public function testAddQueueInsertsMiddlewaresAtCurrentPosition(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware1, $middleware3]);

        $queue = $this->getQueueProperty($dispatcher);
        // Move to position 1 (middleware3)
        $queue->next();

        $dispatcher->addQueue([$middleware2]);

        $queue = $this->getQueueProperty($dispatcher);
        $queueArray = $queue->getArrayCopy();
        $this->assertCount(3, $queueArray);
        $this->assertSame($middleware1, $queueArray[0]);
        $this->assertSame($middleware2, $queueArray[1]);
        $this->assertSame($middleware3, $queueArray[2]);
    }

    public function testAddQueueWhenKeyIsNull(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware]);

        $queue = $this->getQueueProperty($dispatcher);
        // Move beyond the end so key becomes null
        $queue->next();

        $dispatcher->addQueue([
            $middleware2 = $this->createMock(MiddlewareInterface::class),
            $middleware3 = $this->createMock(MiddlewareInterface::class),
        ]);

        // Queue should remain unchanged
        $queue = $this->getQueueProperty($dispatcher);
        $queueArray = $queue->getArrayCopy();
        $this->assertCount(3, $queueArray);
        $this->assertSame($middleware, $queueArray[0]);
        $this->assertSame($middleware2, $queueArray[1]);
        $this->assertSame($middleware3, $queueArray[2]);
    }

    public function testMultipleMiddlewareExecutionOrder(): void
    {
        $executionOrder = [];

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->method('process')
            ->willReturnCallback(function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) use (&$executionOrder) {
                $executionOrder[] = 'middleware1-before';
                $response = $handler->handle($request);
                $executionOrder[] = 'middleware1-after';
                return $response;
            });

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->method('process')
            ->willReturnCallback(function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) use (&$executionOrder) {
                $executionOrder[] = 'middleware2-before';
                $response = $handler->handle($request);
                $executionOrder[] = 'middleware2-after';
                return $response;
            });

        $this->requestHandler->method('handle')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'request-handler';
                return $this->response;
            });

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware1, $middleware2]);

        $result = $dispatcher->handle($this->request);

        $this->assertSame($this->response, $result);
        $this->assertEquals([
            'middleware1-before',
            'middleware2-before',
            'request-handler',
            'middleware2-after',
            'middleware1-after'
        ], $executionOrder);
    }

    public function testQueuePositionIsMaintainedAfterHandle(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware1, $middleware2]);

        $queue = $this->getQueueProperty($dispatcher);

        // First handle should process middleware1 and advance to middleware2
        $dispatcher->handle($this->request);
        $this->assertSame($middleware2, $queue->current());

        // Second handle should process middleware2 and advance beyond
        $dispatcher->handle($this->request);
        $this->assertFalse($queue->valid());
    }

    public function testAddQueueWithEmptyArrayDoesNothing(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware]);

        $queue = $this->getQueueProperty($dispatcher);
        $queue->next(); // Move to position where key is null

        $dispatcher->addQueue([]);

        // Queue should remain unchanged
        $queueArray = $queue->getArrayCopy();
        $this->assertCount(1, $queueArray);
        $this->assertSame($middleware, $queueArray[0]);
    }

    public function testAddQueuePreservesMiddlewareOrder(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $newMiddleware1 = $this->createMock(MiddlewareInterface::class);
        $newMiddleware2 = $this->createMock(MiddlewareInterface::class);

        $dispatcher = new HttpMiddlewareDispatcher($this->requestHandler);
        $dispatcher->setQueue([$middleware1, $middleware2]);

        $queue = $this->getQueueProperty($dispatcher);
        $queue->next();
        $this->assertSame(1, $queue->key());

        $dispatcher->addQueue([$newMiddleware1, $newMiddleware2]);

        $queue = $this->getQueueProperty($dispatcher);
        $queueArray = $queue->getArrayCopy();

        // Проверяем, что позиция сохранилась корректно (с учетом добавленного элемента)
        $this->assertSame(1, $queue->key());
        // Проверяем, что порядок добавленных middleware сохранился: newMiddleware1, newMiddleware2
        // Без array_reverse порядок был бы обратным: newMiddleware2, newMiddleware1
        $this->assertSame($newMiddleware1, $queueArray[1], 'First added middleware should be at position 1');
        $this->assertSame($newMiddleware2, $queueArray[2], 'Second added middleware should be at position 2');
        $this->assertSame($middleware2, $queueArray[3], 'Original middleware should be after added ones');

        // Общий порядок должен быть: [0:middleware1, 1:newMiddleware1, 2:newMiddleware2, 3:middleware2]
        $this->assertCount(4, $queueArray);
    }
}