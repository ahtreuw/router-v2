<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Router;

readonly class Route
{
    public function __construct(
        public string $requestHandler,
        public string $requestController,
        public string $requestMethod,
        public array  $requestParameters,
        public array  $responseHeaders,
        public array  $middlewares,
        public array  $methods
    )
    {
    }
}
