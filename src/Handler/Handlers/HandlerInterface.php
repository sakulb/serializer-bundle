<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Handler\Handlers;

use Sakulb\SerializerBundle\SakulbSerializerBundle;
use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)]
interface HandlerInterface
{
    public static function getPriority(): int;

    public static function supportsSerialize(mixed $value): bool;

    public static function supportsDeserialize(mixed $value, string $type): bool;

    public static function supportsDescribe(string $property, Metadata $metadata): bool;

    /**
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed;

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed;

    public function describe(string $property, Metadata $metadata): array;
}
