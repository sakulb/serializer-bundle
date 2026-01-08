<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Handler;

use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Handler\Handlers\HandlerInterface;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final readonly class HandlerResolver
{
    public function __construct(
        private ContainerInterface $handlerLocator,
        private array $handlers,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function getSerializationHandler(mixed $value, ?string $customHandler): HandlerInterface
    {
        try {
            if ($customHandler) {
                return $this->handlerLocator->get($customHandler);
            }
            /** @var class-string<HandlerInterface> $handler */
            foreach ($this->handlers as $handler) {
                if ($handler::supportsSerialize($value)) {
                    return $this->handlerLocator->get($handler);
                }
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $exception) {
            throw new SerializerException('Unable to get handler.', 0, $exception);
        }

        throw new SerializerException('Unable to determine serialization handler');
    }

    /**
     * @throws SerializerException
     */
    public function getDeserializationHandler(
        mixed $value,
        string $type,
        ?string $customHandler
    ): HandlerInterface {
        try {
            if ($customHandler) {
                return $this->handlerLocator->get($customHandler);
            }
            /** @var class-string<HandlerInterface> $handler */
            foreach ($this->handlers as $handler) {
                if ($handler::supportsDeserialize($value, $type)) {
                    return $this->handlerLocator->get($handler);
                }
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $exception) {
            throw new SerializerException('Unable to get handler.', 0, $exception);
        }

        throw new SerializerException('Unable to determine deserialization handler');
    }

    /**
     * @throws SerializerException
     */
    public function getDescriptionHandler(string $property, Metadata $metadata): HandlerInterface
    {
        try {
            if ($metadata->customHandler) {
                return $this->handlerLocator->get($metadata->customHandler);
            }

            /** @var class-string<HandlerInterface> $handler */
            foreach ($this->handlers as $handler) {
                if ($handler::supportsDescribe($property, $metadata)) {
                    return $this->handlerLocator->get($handler);
                }
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $exception) {
            throw new SerializerException('Unable to get handler.', 0, $exception);
        }

        throw new SerializerException('Unable to determine description handler');
    }
}
