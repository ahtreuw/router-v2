# Router-V2


```php
<?php declare(strict_types=1);

/**
 *
 * @var CacheInterface $cache
 * @var LoggerInterface $logger
 * @var ContainerInterface $container
 * @var ServerRequestFactoryInterface $serverRequestFactory
 * @var ResponseFactoryInterface $responseFactory
 * @var StreamFactoryInterface $streamFactory
 * @var UriFactoryInterface $uriFactory
 */

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Vulpes\RouterV2\RequestHandler;
use Vulpes\RouterV2\Router\Dispatcher;
use Vulpes\RouterV2\SapiEmitter;
use Vulpes\RouterV2\ServerRequestFactory;

try {

    $serverRequestFactory = new ServerRequestFactory(
        serverRequestFactory: $serverRequestFactory,
        streamFactory: $streamFactory,
        uriFactory: $uriFactory
    );

    $serverRequest = $serverRequestFactory->createServerRequest($_SERVER);

    // register if possible
    // $container->set(Psr\Http\Message\ServerRequestInterface::class, $serverRequest);

    $dispatcher = new Dispatcher($responseFactory, $cache);
    $dispatcher->addControllers(
        MyControllerOrRequestHandler::class, AnotherControllerOrRequestHandler::class
    );

    $requestHandler = new RequestHandler(
        container: $container,
        dispatcher: $dispatcher,
        responseFactory: $responseFactory,
    );

    $response = $requestHandler->handle($serverRequest);

    (new SapiEmitter())->emit($response);

} catch (Throwable $exception) {

    ($logger ?? null)?->emergency($exception->getMessage(), [
        'exception' => $exception,
        'request' => $serverRequest ?? null
    ]);

    header("HTTP/1.1 500 Internal Server Error");
    header('Content-Type: text/plain;charset=utf-8');
    print $exception;
}

```