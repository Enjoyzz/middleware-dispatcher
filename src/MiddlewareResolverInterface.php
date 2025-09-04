<?php

namespace Enjoys\MiddlewareDispatcher;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface MiddlewareResolverInterface
{

    /**
     * @param mixed $entry
     * @return null|MiddlewareInterface|callable(ServerRequestInterface, RequestHandlerInterface):ResponseInterface
     */
    public function resolve(mixed $entry): null|MiddlewareInterface|callable;
}
