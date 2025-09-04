<?php

namespace Enjoys\MiddlewareDispatcher;

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
