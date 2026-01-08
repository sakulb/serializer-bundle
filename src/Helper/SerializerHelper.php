<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Helper;

use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class SerializerHelper
{
    public static function getOaFriendlyType(string $type): string
    {
        return match ($type) {
            TypeIdentifier::INT->value => 'integer',
            TypeIdentifier::BOOL->value => 'boolean',
            TypeIdentifier::FLOAT->value => 'number',
            Uuid::class, Ulid::class => TypeIdentifier::STRING->value,
            default => $type,
        };
    }

    public static function getClassBaseName(string $className): string
    {
        return substr((string) strrchr($className, '\\'), 1);
    }
}
