<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class SerializeParam
{
    public function __construct(
        public ?string $type = null,
    ) {
    }
}
