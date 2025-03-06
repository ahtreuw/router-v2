<?php declare(strict_types=1);

namespace Vulpes\RouterV2\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS, Attribute::TARGET_METHOD, Attribute::IS_REPEATABLE)]
class Header
{
    public function __construct(string $name, $value)
    {
    }
}
