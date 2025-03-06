<?php declare(strict_types=1);

namespace Vulpes\RouterV2\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispenser implements RequestHandlerInterface
{
    protected array $middlewares;

    public function __construct(protected RequestHandlerInterface $handler, MiddlewareInterface ...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($middleware = array_shift($this->middlewares)) {
            return $middleware->process($request, $this);
        }
        return $this->handler->handle($request);
    }
}
