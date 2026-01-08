<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\OpenApi;

use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Handler\HandlerResolver;
use Sakulb\SerializerBundle\Helper\SerializerHelper;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Sakulb\SerializerBundle\Metadata\MetadataRegistry;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\ModelDescriber\Annotations\SymfonyConstraintAnnotationReader;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use OpenApi\Annotations\Items;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

final class SerializerModelDescriber implements ModelDescriberInterface
{
    public const string NESTED_CLASS = 'nested_object';

    private ?SymfonyConstraintAnnotationReader $symfonyConstraintAnnotationReader = null;

    public function __construct(
        private readonly MetadataRegistry $metadataRegistry,
        private readonly HandlerResolver $handlerResolver,
    ) {
    }

    public function supports(Model $model): bool
    {
        return false === empty($this->getMetadata($model));
    }

    /**
     * @throws SerializerException|ReflectionException
     */
    public function describe(Model $model, Schema $schema): void
    {
        $schema->type = TypeIdentifier::OBJECT->value;
        $properties = [];
        foreach ($this->getMetadata($model) as $propertyName => $metadata) {
            $handler = $this->handlerResolver->getDescriptionHandler($propertyName, $metadata);
            $description = $handler->describe($propertyName, $metadata);

            // Describe a nested object.
            if (isset($description[self::NESTED_CLASS])) {
                $nestedSchema = $this->describeNested($propertyName, $description);
                if ($nestedSchema) {
                    $properties[] = $nestedSchema;

                    continue;
                }
            }
            $this->describeNestedItems($description);
            $property = new Property($description);

            // Describe symfony constraints and property docBlock description.
            $className = $this->getModelClassName($model);
            if ($metadata->property && $className) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $propertyReflection = new ReflectionProperty($className, $metadata->property);
                $this->getSymfonyConstraintAnnotationReader()->updateProperty($propertyReflection, $property);
                $this->addDocBlockDescription($propertyReflection, $property);
            }

            // Method docBlock description.
            if (null === $metadata->setter && $className) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $methodReflection = new ReflectionMethod($className, $metadata->getter);
                $this->addDocBlockDescription($methodReflection, $property);
            }

            $properties[] = $property;
        }
        $schema->properties = $properties;
    }

    private function getSymfonyConstraintAnnotationReader(): SymfonyConstraintAnnotationReader
    {
        if (null === $this->symfonyConstraintAnnotationReader) {
            $this->symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader();
        }

        return $this->symfonyConstraintAnnotationReader;
    }

    private function addDocBlockDescription(ReflectionProperty|ReflectionMethod $reflection, Property $property): void
    {
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            $docComment = explode(PHP_EOL, $docComment);

            $firstKey = array_key_first($docComment);
            $lastKey = array_key_last($docComment);
            if ($firstKey && $lastKey) {
                unset($docComment[$firstKey], $docComment[$lastKey]);
            }

            $description = '';
            foreach ($docComment as $line) {
                $line = trim(ltrim($line, '* '));
                if ($line) {
                    if (str_starts_with($line, '@')) {
                        continue;
                    }
                    $description .= $line;

                    continue;
                }

                break;
            }
            if ($description) {
                $property->description = $description;
            }
        }
    }

    /**
     * @throws ReflectionException | SerializerException
     */
    private function describeNested(string $property, array $description): ?Property
    {
        $className = $description[self::NESTED_CLASS];
        $nestedModel = new Model(new Type\ObjectType($className));
        $nestedSchema = new Property([
            'property' => $property,
            'title' => SerializerHelper::getClassBaseName($className),
            'readOnly' => $description['readOnly'] ?? false,
            'nullable' => $description['nullable'] ?? false,
        ]);
        if ($this->supports($nestedModel)) {
            $this->describe($nestedModel, $nestedSchema);

            return $nestedSchema;
        }

        return null;
    }

    /**
     * @throws ReflectionException | SerializerException
     */
    private function describeNestedItems(array &$description): void
    {
        if (isset($description['items'][self::NESTED_CLASS])) {
            $nestedItems = new Items(['title' => SerializerHelper::getClassBaseName($description['items'][self::NESTED_CLASS])]);
            $nestedItemsModel = new Model(new Type\ObjectType($description['items'][self::NESTED_CLASS]));

            if ($this->supports($nestedItemsModel)) {
                $this->describe($nestedItemsModel, $nestedItems);
            }

            $description['items'] = $nestedItems;
        }
    }

    /**
     * @return array<string, Metadata>
     */
    private function getMetadata(Model $model): array
    {
        $className = $this->getModelClassName($model);
        if ($className) {
            try {
                return $this->metadataRegistry->get($className);
            } catch (SerializerException) {
                return [];
            }
        }

        return [];
    }

    private function getModelClassName(Model $model): ?string
    {
        $typeInfo = $model->getTypeInfo();
        if ($typeInfo instanceof Type\ObjectType) {
            $className = $typeInfo->getClassName();
            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }
}
