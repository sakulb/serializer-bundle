<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Handler\Handlers;

use Sakulb\SerializerBundle\Attributes\Serialize;
use Sakulb\SerializerBundle\Helper\SerializerHelper;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Sakulb\SerializerBundle\OpenApi\SerializerModelDescriber;
use Sakulb\SerializerBundle\Service\JsonDeserializer;
use Sakulb\SerializerBundle\Service\JsonSerializer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Symfony\Component\TypeInfo\TypeIdentifier;

final class ObjectHandler extends AbstractHandler
{
    public function __construct(
        private readonly JsonSerializer $jsonSerializer,
        private readonly JsonDeserializer $jsonDeserializer,
    ) {
    }

    public static function getPriority(): int
    {
        return -1;
    }

    public static function supportsSerialize(mixed $value): bool
    {
        return is_object($value) || is_array($value);
    }

    /**
     * @inheritDoc
     *
     * @param object|array $value
     */
    public function serialize(mixed $value, Metadata $metadata): array|object
    {
        if ($metadata->orderBy && $value instanceof Selectable) {
            $criteria = Criteria::create()
                ->orderBy($metadata->orderBy);

            return $this->jsonSerializer->toArray($value->matching($criteria), $metadata);
        }

        return $this->jsonSerializer->toArray($value, $metadata);
    }

    public static function supportsDeserialize(mixed $value, string $type): bool
    {
        return is_array($value);
    }

    /**
     * @inheritDoc
     *
     * @param array $value
     */
    public function deserialize(mixed $value, Metadata $metadata): object|iterable
    {
        if (is_a($metadata->type, Collection::class, true)) {
            /** @var Collection<int|string, iterable|object> $collection */
            $collection = new ArrayCollection();
            foreach ($value as $key => $item) {
                $collection->set($key, $this->jsonDeserializer->fromArray($item, $this->getDeserializeCustomType($item, $metadata) ?? $metadata->type));
            }

            return $collection;
        }
        if (TypeIdentifier::ARRAY->value === $metadata->type) {
            if ($metadata->customType || $metadata->discriminatorMap) {
                $array = [];
                foreach ($value as $key => $item) {
                    $array[$key] = $this->jsonDeserializer->fromArray($item, $this->getDeserializeCustomType($item, $metadata) ?? $metadata->type);
                }

                return $array;
            }
            return $value;
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->jsonDeserializer->fromArray($value, $this->getDeserializeCustomType($value, $metadata) ?? $metadata->type);
    }

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return true;
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);
        if (is_a($metadata->type, Collection::class, true)
            || TypeIdentifier::ARRAY->value === $metadata->type) {
            $description['type'] = TypeIdentifier::ARRAY->value;
            $description['items'] = null;
            if (Serialize::KEYS_VALUES === $metadata->strategy) {
                $description['type'] = TypeIdentifier::OBJECT->value;
                $description['title'] = 'Custom key-value data.';

                return $description;
            }
            if ($metadata->customType && class_exists($metadata->customType)) {
                $description['title'] = 'Array of ' . SerializerHelper::getClassBaseName($metadata->customType);
                $description['items'] = [
                    'type' => TypeIdentifier::OBJECT->value,
                    SerializerModelDescriber::NESTED_CLASS => $metadata->customType,
                ];
            }

            return $description;
        }
        $description[SerializerModelDescriber::NESTED_CLASS] = $metadata->type;

        return $description;
    }

    /**
     * @return class-string|null
     *
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */
    private function getDeserializeCustomType(mixed $item, Metadata $metadata): string|null
    {
        if ($metadata->discriminatorMap && array_key_exists(Serialize::DISCRIMINATOR_COLUMN, $item)) {
            return $metadata->discriminatorMap[
                $item[Serialize::DISCRIMINATOR_COLUMN]
            ];
        }

        return $metadata->customType;
    }
}
