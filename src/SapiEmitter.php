<?php declare(strict_types=1);

namespace Vulpes\RouterV2;

use Psr\Http\Message\ResponseInterface;

class SapiEmitter
{
    public function emit(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $protocolVersion = $response->getProtocolVersion();

        $this->header(rtrim(sprintf('HTTP/%s %d %s', $protocolVersion, $statusCode, $reasonPhrase)), true, $statusCode);

        foreach ($response->getHeaders() as $header => $values) {
            $replace = ($name = ucwords($header, '-')) !== 'Set-Cookie';
            foreach ($values as $value) {
                $this->header(sprintf('%s: %s', $name, $value), $replace, $statusCode);
                $replace = false;
            }
        }

        echo $response->getBody();
    }

    protected function header(string $header, bool $replace, int $responseCode): void
    {
        header($header, $replace, $responseCode);
    }
}
