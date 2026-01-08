<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Attributes;

use Sakulb\SerializerBundle\Metadata\ContainerParam;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Serialize
{
    public const string KEYS_VALUES = 'kv';
    public const string DISCRIMINATOR_COLUMN = 'discriminator';

    /**
     * @param null|array<string, 'ASC'|'DESC'> $orderBy
     */
    public function __construct(
        public ?string $serializedName = null,
        public ?string $handler = null,
        public null|string|ContainerParam $type = null,
        public ?string $strategy = null,
        public ?string $persistedName = null,
        public ?array $discriminatorMap = null,
        public ?array $orderBy = null,
    ) {
    }
}
