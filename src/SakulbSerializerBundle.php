<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle;

use Sakulb\SerializerBundle\DependencyInjection\SakulbSerializerExtension;
use Sakulb\SerializerBundle\DependencyInjection\CompilerPass\SerializerHandlerCompilerPass;
use Sakulb\SerializerBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SakulbSerializerBundle extends AbstractBundle
{
    public const string TAG_SERIALIZER_HANDLER = Configuration::ALIAS . '.handler';

    public function build(ContainerBuilder $container): void
    {
        $container->registerExtension(new SakulbSerializerExtension());
        $container->addCompilerPass(new SerializerHandlerCompilerPass());
    }
}
