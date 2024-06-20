<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\Tests;

use Sakulb\SerializerBundle\Serializer;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractTestCase extends KernelTestCase
{
    protected Serializer $serializer;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(Serializer::class);
    }

    protected static function getKernelClass(): string
    {
        return SakulbTestKernel::class;
    }
}
