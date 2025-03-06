<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS, Attribute::TARGET_METHOD)]
class Middleware
{
    public function __construct(string|object ...$middlewares)
    {
    }
}
