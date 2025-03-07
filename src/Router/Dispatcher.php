<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Router;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Vulpes\RouterV2\Attribute\Header;
use Vulpes\RouterV2\Attribute\Headers;
use Vulpes\RouterV2\Attribute\Middleware;
use Vulpes\RouterV2\Attribute\Path;
use Vulpes\RouterV2\Attribute\RequestHandler as RequestHandlerAttribute;
use Vulpes\RouterV2\HttpException;
use Vulpes\RouterV2\RequestHandler\BasicRequestHandler;

class Dispatcher implements DispatcherInterface
{
    /**
     * @var string[]
     */
    protected array $controllers = [];

    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected CacheInterface|null      $cache = null,
    )
    {
    }

    public function addControllers(string ...$controllers): void
    {
        $this->controllers = array_merge($this->controllers, $controllers);
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface|Route
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        try {
            $version = $request->getAttribute('APP_VERSION') ?: '0.0';
            foreach ($this->getRoutes((string)$version) as $basicPath => $route) {
                if ($basicPath !== $path && !preg_match($route['pattern'], $path, $matches)) {
                    continue;
                }
                if (!array_key_exists($method, $route['methods'])) {
                    if ($method !== Path::OPTIONS) {
                        throw new HttpException('Method Not Allowed', 405);
                    }
                    return $this->createOptionsResponse([...array_keys($route['methods']), Path::OPTIONS]);
                }
                return $this->createRoute($method, $route, $matches ?? null);
            }
            throw new HttpException('Not Found', 404);

        } catch (HttpException $exception) {

            $response = $this->responseFactory->createResponse($exception->getCode());
            $response->getBody()->write(json_encode(['error' => [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage()
            ]]));
            return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
        }
    }

    /**
     * @param string|null $version
     * @return array{
     *   path: string,
     *   pattern: string,
     *   methods: array
     * }
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    private function getRoutes(null|string $version): array
    {
        $cacheKey = 'dispatcher:routes:' . ($version ?: 'na');
        if ($version && $this->cache?->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        $routes = $this->generateRoutes();
        $this->cache?->set($cacheKey, $routes, 3600);
        return $routes;
    }

    public function convertRegexToPRegex(string $pattern, ReflectionClass|ReflectionMethod $reflector): array
    {
        if (preg_match_all('/(\[\^\/\]\+)/', $pattern, $matches)) {
            $pattern = preg_replace('/\[\^\/\]\+/', '([^\/]+)', $pattern);
            $pattern = '\/' . str_replace(['/(', ')/'], ['\/(', ')\/'], trim($pattern, '/'));
            return ["/^$pattern$/", $this->getParameters(array_keys($matches[1] ?? []), $reflector)];
        }
        $pattern = '\/' . str_replace('/', '\/', trim($pattern, '/'));
        return ["/^$pattern$/", []];
    }

    private function getHeaders(ReflectionClass|ReflectionMethod $reflector): array
    {
        $headers = [];
        if ($reflector instanceof ReflectionClass && ($parent = $reflector->getParentClass())) {
            $headers = array_merge($headers, $this->getHeaders($parent));
        }
        if ($reflector instanceof ReflectionMethod) {
            $headers = array_merge($headers, $this->getHeaders($reflector->getDeclaringClass()));
        }
        foreach ($reflector->getAttributes(Headers::class) as $attribute) {
            $headers = array_merge($headers, $attribute->getArguments()[0]);
        }
        foreach ($reflector->getAttributes(Header::class) as $attribute) {
            [$name, $value] = $attribute->getArguments();
            $headers[$name] = $value;
        }
        return $headers;
    }

    private function getMiddlewares(ReflectionClass|ReflectionMethod $reflector): array
    {
        $middlewares = [];
        if ($reflector instanceof ReflectionClass && ($parent = $reflector->getParentClass())) {
            $middlewares = array_merge($middlewares, $this->getMiddlewares($parent));
        }
        if ($reflector instanceof ReflectionMethod) {
            $middlewares = array_merge($middlewares, $this->getMiddlewares($reflector->getDeclaringClass()));
        }
        foreach ($reflector->getAttributes(Middleware::class) as $attribute) {
            $middlewares = array_merge($middlewares, $attribute->getArguments());
        }
        return $middlewares;
    }

    /**
     * @return ReflectionClass[]|ReflectionMethod[]
     * @throws ReflectionException
     */
    private function getReflectorsWithPathAttribute(string $requestHandler): array
    {
        $reflector = new ReflectionClass($requestHandler);
        $reflectors = [];
        if ($reflector->getAttributes(Path::class)) {
            return [$reflector];
        }
        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || str_starts_with($method->getName(), '__')) {
                continue;
            }
            if ($method->getAttributes(Path::class)) {
                $reflectors[] = $method;
            }
        }
        return $reflectors;
    }

    /**
     * @param ReflectionClass|ReflectionMethod $reflector
     * @return array{string, string, string, string}
     */
    private function getInfo(ReflectionClass|ReflectionMethod $reflector): array
    {
        [$httpMethod, $path] = $reflector->getAttributes(Path::class)[0]->getArguments();

        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '[^/]+', $path);

        $requestMethod = $reflector instanceof ReflectionMethod ? $reflector->getShortName() : 'index';
        $controller = $reflector instanceof ReflectionMethod ? $reflector->getDeclaringClass()->getName() : $reflector->getName();

        return [$httpMethod, $regex, $requestMethod, $controller];
    }

    private function getParameters(array $parameters, ReflectionMethod|ReflectionClass $reflector): array
    {
        if ($reflector instanceof ReflectionClass) {
            return $parameters;
        }
        if ($reflector->getNumberOfParameters() !== count($parameters)) {
            return $parameters;
        }
        $parameters = [];
        foreach ($reflector->getParameters() as $parameter) {
            $parameters[] = $parameter->getName();
        }
        return $parameters;
    }

    private function getRequestHandler(ReflectionMethod|ReflectionClass $reflector): string
    {
        if ($reflector instanceof ReflectionClass &&
            $reflector->implementsInterface(RequestHandlerInterface::class)) {
            return $reflector->getName();
        }
        if ($attributes = $reflector->getAttributes(RequestHandlerAttribute::class)) {
            return $attributes[0]->getArguments()[0];
        }
        if ($parent = $reflector instanceof ReflectionClass ?
            $reflector->getParentClass() : $reflector->getDeclaringClass()) {
            return $this->getRequestHandler($parent);
        }
        return BasicRequestHandler::class;
    }

    /**
     * @throws ReflectionException
     */
    public function generateRoutes(): array
    {
        $routes = [];
        foreach ($this->controllers as $requestHandler) {
            foreach ($this->getReflectorsWithPathAttribute($requestHandler) as $reflector) {
                [$httpMethod, $regex, $requestMethod, $controller] = $this->getInfo($reflector);
                [$pattern, $parameters] = $this->convertRegexToPRegex($regex, $reflector);
                $routes[$regex] = [
                    'pattern' => $pattern,
                    'methods' => array_merge($routes[$regex]['methods'] ?? [], [$httpMethod => [
                        'requestHandler' => $this->getRequestHandler($reflector),
                        'requestController' => $controller,
                        'requestMethod' => $requestMethod,
                        'requestParameters' => $parameters,
                        'responseHeaders' => $this->getHeaders($reflector),
                        'middlewares' => $this->getMiddlewares($reflector),
                    ]])
                ];
            }
        }
        return $routes;
    }

    private function createRoute(string $method, mixed $route, array|null $matches): Route
    {
        $matches = $matches ?: [null];
        array_shift($matches);

        $parameters = [];

        foreach ($route['methods'][$method]['requestParameters'] as $key) {
            $parameters[$key] = array_shift($matches);
        }

        $route['methods'][$method]['requestParameters'] = $parameters;
        $route['methods'][$method]['methods'] = [...array_keys($route['methods']), Path::OPTIONS];

        return new Route(...$route['methods'][$method]);
    }

    private function createOptionsResponse(array $allowMethods): ResponseInterface
    {
        return $this->responseFactory->createResponse(204)
            ->withheader('Access-Control-Allow-Methods', implode(',', $allowMethods))
            ->withheader('Access-Control-Allow-Credentials', 'true')
            ->withheader('Access-Control-Allow-Headers', 'Content-Type,Authorization');
    }
}
