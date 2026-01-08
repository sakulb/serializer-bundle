<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Handler\Handlers;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\Proxy;
use ReflectionProperty;
use Sakulb\SerializerBundle\Attributes\Serialize;
use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Helper\SerializerHelper;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use stdClass;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class EntityIdHandler extends AbstractHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata): array|object|int|null|string
    {
        if (null === $value) {
            return null;
        }
        $toIdFunction = function (object $item) use ($metadata): null|int|string|object {
            if ($metadata->getterSetterStrategy) {
                return $item->getId();
            }
            if ($item instanceof Proxy) {
                return $this->entityManager->getUnitOfWork()->getEntityIdentifier($item)['id'] ?? null;
            }
            return $item->id;
        };

        if (is_array($value)) {
            $ids = array_map($toIdFunction, $value);
            if (Serialize::KEYS_VALUES === $metadata->strategy) {
                if (empty($ids)) {
                    return new stdClass();
                }

                return $ids;
            }

            return array_values($ids);
        }
        if ($value instanceof Collection) {
            $ids = $value->map($toIdFunction);
            if ($metadata->orderBy) {
                $ids = $this->getOrderedIDs($ids->getValues(), $metadata);
            }
            if (Serialize::KEYS_VALUES === $metadata->strategy) {
                if ($ids->isEmpty()) {
                    return new stdClass();
                }

                return $ids->toArray();
            }

            return $ids->getValues();
        }
        if ($this->isIdentifiable($value, $metadata)) {
            return $toIdFunction($value);
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }

    private function isIdentifiable(mixed $value, Metadata $metadata): bool
    {
        if ($metadata->getterSetterStrategy) {
            return method_exists($value, 'getId');
        }

        return property_exists($value, 'id');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        if (null === $value) {
            return null;
        }
        if (is_iterable($value)) {
            $entities = [];
            foreach ($value as $id) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $entity = $this->entityManager->find((string) $metadata->customType, $id);
                if ($entity) {
                    $entities[] = $entity;
                }
            }
            if (is_a($metadata->type, Collection::class, true)) {
                return new ArrayCollection($entities);
            }

            return $entities;
        }
        $deserializeType = $metadata->customType ?? $metadata->type;
        /** @psalm-suppress ArgumentTypeCoercion */
        if ($this->isIdentifiable($deserializeType, $metadata) && (is_int($value) || is_string($value))) {
            return $this->entityManager->find($deserializeType, $value);
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);
        if (is_a($metadata->type, Collection::class, true)
            || TypeIdentifier::ARRAY->value === $metadata->type) {
            $description['type'] = TypeIdentifier::ARRAY->value;
            $description['title'] = SerializerHelper::getClassBaseName((string) $metadata->customType) . ' IDs';
            $description['items'] = ['type' => $this->describeReturnType((string) $metadata->customType, $metadata->getterSetterStrategy)];

            return $description;
        }

        $description['type'] = $this->describeReturnType($metadata->type, $metadata->getterSetterStrategy);
        $description['title'] = SerializerHelper::getClassBaseName($metadata->type) . ' ID';

        return $description;
    }

    private function getOrderedIDs(array $ids, Metadata $metadata): Collection
    {
        $uidClass = null;
        $ids = array_map(function (null|int|string|object $id) use (&$uidClass) {
            if (class_exists(AbstractUid::class)) {
                if ($id instanceof Uuid) {
                    $uidClass = Uuid::class;

                    return $id->toBinary();
                }
                if ($id instanceof Ulid) {
                    $uidClass = Ulid::class;

                    return $id->toBinary();
                }
            }

            return $id;
        }, $ids);
        /** @psalm-suppress ArgumentTypeCoercion */
        $dqb = $this->entityManager->getRepository((string) $metadata->customType)->createQueryBuilder('entity');
        $dqb
            ->select('entity.id')
            ->where('entity.id IN (:ids)')
            ->setParameter('ids', $ids)
        ;
        if (null !== $metadata->orderBy) {
            foreach ($metadata->orderBy as $field => $direction) {
                $dqb->addOrderBy('entity.' . $field, $direction);
            }
        }
        $resultIds = array_map(function (null|int|string $id) use ($uidClass) {
            return match ($uidClass) {
                Uuid::class => Uuid::fromString((string)$id),
                Ulid::class => Ulid::fromString((string)$id),
                default => $id,
            };
        }, $dqb->getQuery()->getSingleColumnResult());

        return new ArrayCollection($resultIds);
    }

    private function describeReturnType(string $type, bool $getterSetterStrategy): string
    {
        try {
            $reflection = $getterSetterStrategy
                ? new ReflectionMethod($type, 'getId')
                : new ReflectionProperty($type, 'id')
            ;
        } catch (ReflectionException) {
            return SerializerHelper::getOaFriendlyType($type);
        }
        $returnType = $getterSetterStrategy ? $reflection->getReturnType() : $reflection->getType();
        if ($returnType instanceof ReflectionNamedType) {
            return SerializerHelper::getOaFriendlyType($returnType->getName());
        }

        return SerializerHelper::getOaFriendlyType($type);
    }
}
