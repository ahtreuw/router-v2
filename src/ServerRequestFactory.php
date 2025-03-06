<?php declare(strict_types=1);

namespace Vulpes\RouterV2;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Vulpes\RouterV2\Attribute\Path;

readonly class ServerRequestFactory
{
    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private StreamFactoryInterface        $streamFactory,
        private UriFactoryInterface           $uriFactory,
    )
    {
    }

    public function createServerRequest(array $serverParams): ServerRequestInterface
    {
        [$method, $uri, $queryParams, $protocol] = $this->parseServerParams($serverParams);

        $serverRequest = $this->serverRequestFactory->createServerRequest($method, $uri, $serverParams);

        return $this->withBody($this->withHeaders($serverRequest))
            ->withProtocolVersion($protocol)
            ->withQueryParams($queryParams)
            ->withCookieParams($_COOKIE ?? []);
    }

    /**
     * @return array{string, UriInterface, string[], string}
     */
    private function parseServerParams(array $server): array
    {
        $uri = $this->uriFactory->createUri($this->serverParamsToUri(...$server));

        parse_str($uri->getQuery(), $queryParams);

        if (is_null($method = $server['REQUEST_METHOD'] ?? null)) {
            $method = strtoupper($server['argv'][1] ?? '');
            $method = in_array($method, Path::METHODS) ?
                $method : (php_sapi_name() !== 'cli' ? Path::GET : Path::CLI);
        }

        if (php_sapi_name() !== 'cli' && $method === 'CLI') {
            $method = 'GET';
        }

        $protocol = str_replace('HTTP/', '', $server['SERVER_PROTOCOL'] ?? 'HTTP/1.1');

        return [strtoupper($method), $uri, $queryParams, $protocol];
    }

    protected function serverParamsToUri(
        null|string $REQUEST_SCHEME = null,
        null|string $HTTPS = null,
        null|string $HTTP_HOST = null,
        null|string $SERVER_NAME = null,
        null|string $SERVER_ADDR = null,
        null|string $SERVER_PORT = null,
        null|string $REQUEST_URI = null,
        null|string $QUERY_STRING = null,
        null|array  $argv = null,
        mixed       ...$extra
    ): string
    {
        $scheme = $REQUEST_SCHEME ?? ($HTTPS !== 'off' ? 'https' : 'http');

        $host = parse_url("$scheme://$HTTP_HOST", PHP_URL_HOST) ?: ($SERVER_NAME ?? $SERVER_ADDR ?? 'localhost');
        $port = parse_url("$scheme://$HTTP_HOST", PHP_URL_PORT) ?: $SERVER_PORT;

        $offset = in_array(strtoupper($argv[1] ?? ''), Path::METHODS) ? 2 : 1;
        $path = trim(preg_replace('/\/+/', '/', $REQUEST_URI ?? implode('/', array_slice($argv ?? [], $offset))), '/');

        $query = (!str_contains($path, '?') && $QUERY_STRING) ? "?$QUERY_STRING" : '';

        return "$scheme://$host" . ($port ? ":$port" : '') . "/$path$query";
    }

    protected function withHeaders(ServerRequestInterface $serverRequest): ServerRequestInterface
    {
        foreach (getallheaders() as $name => $value) {
            $serverRequest = $serverRequest->withHeader($name, $value);
        }
        return $serverRequest;
    }

    protected function withBody(ServerRequestInterface $request): ServerRequestInterface
    {
        $body = $this->streamFactory->createStreamFromFile('php://input', 'r+');
        if ($_POST ?? null) {
            return $request->withBody($body)->withParsedBody($_POST);
        }
        if (str_contains($request->getHeaderLine('Content-Type'), 'application/json') === false) {
            return $request->withBody($body);
        }
        if ($requestBody = $body->getContents()) {
            return $request->withBody($body)->withParsedBody(json_decode($requestBody, true));
        }
        return $request->withBody($body)->withParsedBody([]);
    }
}
