<?php declare(strict_types=1);

namespace Vulpes\RouterV2;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;
use Vulpes\RouterV2\RequestHandler\MiddlewareDispenser;
use Vulpes\RouterV2\Router\DispatcherInterface;
use Vulpes\RouterV2\Router\Route;

class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        protected ContainerInterface       $container,
        protected DispatcherInterface      $dispatcher,
        protected ResponseFactoryInterface $responseFactory,
    )
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $this->dispatcher->dispatch($request);

        if ($route instanceof ResponseInterface) {
            return $route;
        }

        $requestHandler = $this->createRequestHandler($route);

        if ($route->requestHandler !== $route->requestController) {
            $request = $this->withController($request, $route);
        }

        $middlewareDispenser = new MiddlewareDispenser($requestHandler, ...$this->withMiddlewares($route));

        $response = $middlewareDispenser->handle($this->withParameters($request, $route));

        return $this->responseWithHeaders($response, $route);
    }

    protected function responseWithHeaders(ResponseInterface $response, Route $route): ResponseInterface
    {
        foreach ($route->responseHeaders as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        $accessControl = [
            'Access-Control-Allow-Methods' => implode(',', $route->methods),
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization'];

        foreach ($accessControl as $name => $values) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $values);
            }
        }
        return $response;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function createRequestHandler(Route $route): RequestHandlerInterface
    {
        return $this->container->get($route->requestHandler);
    }

    /**
     * @return MiddlewareInterface[]
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function withMiddlewares(Route $route): array
    {
        $middlewares = [];
        foreach ($route->middlewares as $middleware) {
            $middlewares[] = $this->container->get($middleware);
        }
        return $middlewares;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function withController(ServerRequestInterface $request, Route $route): ServerRequestInterface
    {
        return $request
            ->withAttribute('requestController', $this->container->get($route->requestController))
            ->withAttribute('requestMethod', $route->requestMethod)
            ->withAttribute('requestParameters', $route->requestParameters);
    }

    private function withParameters(ServerRequestInterface $request, Route $route): ServerRequestInterface
    {
        foreach ($route->requestParameters as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        return $request;
    }
}
