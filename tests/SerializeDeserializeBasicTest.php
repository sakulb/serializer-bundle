<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Sakulb\SerializerBundle\Exception\SerializerException;
use Sakulb\SerializerBundle\Tests\TestApp\Entity\Example;
use Sakulb\SerializerBundle\Tests\TestApp\Model\ExampleBackedEnum;
use Sakulb\SerializerBundle\Tests\TestApp\Model\ExampleUnitEnum;
use DateTimeImmutable;

final class SerializeDeserializeBasicTest extends AbstractTestCase
{
    /**
     * @throws SerializerException
     */
    #[DataProvider('data')]
    public function testSerializeBasic(string $json, Example $data): void
    {
        $serialized = $this->serializer->serialize($data);
        self::assertEquals($json, $serialized);
    }

    /**
     * @throws SerializerException
     */
    #[DataProvider('data')]
    public function testDeSerializeBasic(string $json, Example $data): void
    {
        $deserialized = $this->serializer->deserialize($json, $data::class);
        self::assertEquals($data, $deserialized);
    }

    public static function data(): iterable
    {
        yield [
            '{"id":1,"name":"Test name","createdAt":"2023-12-31T12:34:56Z","place":"first","color":"Red"}',
            new Example()
                ->setId(1)
                ->setName('Test name')
                ->setCreatedAt(new DateTimeImmutable('2023-12-31T12:34:56Z'))
                ->setPlace(ExampleBackedEnum::First)
                ->setColor(ExampleUnitEnum::Red)
        ];

        yield [
            '{"id":2,"name":"Another","createdAt":"2022-12-31T00:00:00Z","place":"second","color":"Green"}',
            new Example()
                ->setId(2)
                ->setName('Another')
                ->setCreatedAt(new DateTimeImmutable('2022-12-31T00:00:00Z'))
                ->setPlace(ExampleBackedEnum::Second)
                ->setColor(ExampleUnitEnum::Green)
        ];
    }
}
