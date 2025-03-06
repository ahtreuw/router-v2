<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;

interface DispatcherInterface
{
    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface|Route;
}
