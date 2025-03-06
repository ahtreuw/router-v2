<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS, Attribute::TARGET_METHOD)]
class Path
{
    public const string GET = 'GET';
    public const string HEAD = 'HEAD';
    public const string OPTIONS = 'OPTIONS';
    public const string TRACE = 'TRACE';
    public const string PUT = 'PUT';
    public const string DELETE = 'DELETE';
    public const string POST = 'POST';
    public const string PATCH = 'PATCH';
    public const string CONNECT = 'CONNECT';
    public const string CLI = 'CLI';

    public const array METHODS = [
        self::GET,
        self::HEAD,
        self::OPTIONS,
        self::TRACE,
        self::PUT,
        self::DELETE,
        self::POST,
        self::PATCH,
        self::CONNECT,
        self::CLI
    ];

    public function __construct(string $method, string $path)
    {
    }
}
