<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Service;

use Sakulb\SerializerBundle\Attributes\Serialize;
use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Handler\HandlerResolver;
use Sakulb\SerializerBundle\Metadata\Metadata;
use Sakulb\SerializerBundle\Metadata\MetadataRegistry;
use JsonException;

final readonly class JsonSerializer
{
    public function __construct(
        private HandlerResolver $handlerResolver,
        private MetadataRegistry $metadataRegistry,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function serialize(object|iterable $data): string
    {
        $dataArray = $this->toArray($data);

        try {
            return json_encode($dataArray, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new SerializerException('Cannot encode json data.', 0, $jsonException);
        }
    }

    /**
     * @throws SerializerException
     */
    public function toArray(object|iterable $data, ?Metadata $metadata = null): array|object
    {
        if (is_iterable($data)) {
            $output = [];
            foreach ($data as $key => $item) {
                $output[$key] = (is_scalar($item) || null === $item) ? $item : $this->toArray($item, $metadata);
            }
            if (Serialize::KEYS_VALUES === $metadata?->strategy) {
                if (empty($output)) {
                    return new \stdClass();
                }

                return $output;
            }

            return array_values($output);
        }

        return $this->objectToArray($data);
    }

    /**
     * @throws SerializerException
     */
    private function objectToArray(object $data): array
    {
        $output = [];
        foreach ($this->metadataRegistry->get($data::class) as $name => $metadata) {
            $value = $metadata->getterSetterStrategy ? $data->{$metadata->getter}() : $data->{$metadata->property};
            $output[$name] = $this->handlerResolver
                ->getSerializationHandler($value, $metadata->customHandler)
                ->serialize($value, $metadata)
            ;
        }

        return $output;
    }
}
