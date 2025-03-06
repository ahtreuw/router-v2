<?php declare(strict_types=1);

namespace Vulpes\RouterV2\RequestHandler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Vulpes\RouterV2\HttpException;

class TextRequestHandler implements RequestHandlerInterface
{
    const string TEXT_PLAIN = 'text/plain;charset=utf-8';

    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected LoggerInterface|null     $logger = null,
    )
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {

            $message = $request->getAttribute('requestController')
                ->{$request->getAttribute('requestMethod')}(...$request->getAttribute('requestParameters'));

            if ($message instanceof ResponseInterface) {
                return $message->withHeader('Content-Type', self::TEXT_PLAIN);
            }

            return $this->createResponse(500, (string)$message);

        } catch (HttpException $exception) {

            return $this->createResponse($exception->getCode(), "{$exception->getCode()} {$exception->getMessage()}");

        } catch (Throwable $exception) {

            $this->logger->error($request->getUri()->getPath(), [
                'exception' => $exception,
                'request' => $request,
            ]);

            return $this->createResponse(500, (string)$exception);
        }
    }

    private function createResponse(int $code, string $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', self::TEXT_PLAIN);
    }
}
