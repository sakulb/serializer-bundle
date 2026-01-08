<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Handler\Handlers;

use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class UidHandler extends AbstractHandler
{
    public static function supportsSerialize(mixed $value): bool
    {
        return $value instanceof AbstractUid;
    }

    /**
     * @param AbstractUid $value
     */
    public function serialize(mixed $value, Metadata $metadata): string
    {
        return $value->toString();
    }

    public static function supportsDeserialize(mixed $value, string $type): bool
    {
        return is_a($type, AbstractUid::class, true);
    }

    public function deserialize(mixed $value, Metadata $metadata): ?AbstractUid
    {
        if (null === $value) {
            return null;
        }
        if (Ulid::isValid($value)) {
            return Ulid::fromString($value);
        }
        if (Uuid::isValid($value)) {
            return Uuid::fromString($value);
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return is_a($metadata->type, AbstractUid::class, true);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);
        $description['type'] = TypeIdentifier::STRING->value;
        $description['title'] = 'UID';
        $description['format'] = 'uid';

        return $description;
    }
}
