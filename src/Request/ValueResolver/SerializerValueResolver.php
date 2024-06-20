<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Request\ValueResolver;

use Sakulb\SerializerBundle\Attributes\SerializeParam;
use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class SerializerValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly Serializer $serializer
    ) {
    }

    /**
     * @return array{0?: iterable<int|string, object>|null|object}
     *
     * @throws SerializerException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(SerializeParam::class)[0] ?? null;
        if (false === ($attribute instanceof SerializeParam)) {
            return [];
        }

        if ($argument->isNullable() && empty($request->getContent())) {
            return [null];
        }

        /** @var class-string $class */
        $class = $argument->getType();
        $type = $attribute->type ?? $class;

        /** @psalm-suppress ArgumentTypeCoercion */
        return [$this->getValue($request, $type)];
    }

    /**
     * @template T
     *
     * @param class-string<T> $type
     *
     * @return T|iterable<int|string, T>
     *
     * @throws SerializerException
     *
     * @psalm-suppress MismatchingDocblockReturnType
     */
    private function getValue(Request $request, string $type): object
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            return $this->serializer->fromArray($request->query->all(), $type);
        }
        $content = (string) $request->getContent();
        if (empty($content)) {
            return new $type();
        }

        return $this->serializer->deserialize($content, $type);
    }
}
