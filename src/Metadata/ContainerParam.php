<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Metadata;

final class ContainerParam
{
    public function __construct(
        public string $paramName
    ) {
    }
}
