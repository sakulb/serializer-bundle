<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Tests\TestApp\Model;

enum ExampleBackedEnum: string
{
    case First = 'first';
    case Second = 'second';
    case Third = 'third';
}
