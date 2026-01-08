<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\DependencyInjection;

use Sakulb\SerializerBundle\SakulbSerializerBundle;
use Sakulb\SerializerBundle\Handler\HandlerResolver;
use Sakulb\SerializerBundle\Handler\Handlers\ArrayStringHandler;
use Sakulb\SerializerBundle\Handler\Handlers\BasicHandler;
use Sakulb\SerializerBundle\Handler\Handlers\DateTimeHandler;
use Sakulb\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Sakulb\SerializerBundle\Handler\Handlers\EnumHandler;
use Sakulb\SerializerBundle\Handler\Handlers\HandlerInterface;
use Sakulb\SerializerBundle\Handler\Handlers\ObjectHandler;
use Sakulb\SerializerBundle\Handler\Handlers\UidHandler;
use Sakulb\SerializerBundle\Metadata\MetadataFactory;
use Sakulb\SerializerBundle\Metadata\MetadataRegistry;
use Sakulb\SerializerBundle\OpenApi\SerializerModelDescriber;
use Sakulb\SerializerBundle\Request\ValueResolver\SerializerValueResolver;
use Sakulb\SerializerBundle\Serializer;
use Sakulb\SerializerBundle\Service\JsonDeserializer;
use Sakulb\SerializerBundle\Service\JsonSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\Uid\Uuid;

final class SakulbSerializerExtension extends Extension
{
    public const string SERIALIZER_PARAMETER_BAG_ID = Configuration::ALIAS . '.' . Configuration::CONFIG_PARAMETER_BAG;

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadSerializer($container, $config);
    }

    private function loadSerializer(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(
            BasicHandler::class,
            new Definition(BasicHandler::class)
                ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
        );
        $container->setDefinition(
            DateTimeHandler::class,
            new Definition(DateTimeHandler::class)
                ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
                ->setArgument('$serializerDateFormat', $config[Configuration::CONFIG_DATE_FORMAT])
        );
        $container->setDefinition(
            EnumHandler::class,
            new Definition(EnumHandler::class)
                ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
        );
        if (class_exists(Uuid::class)) {
            $container->setDefinition(
                UidHandler::class,
                new Definition(UidHandler::class)
                    ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
            );
        }
        $container->setDefinition(
            ObjectHandler::class,
            new Definition(ObjectHandler::class)
                ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
                ->setArgument('$jsonSerializer', new Reference(JsonSerializer::class))
                ->setArgument('$jsonDeserializer', new Reference(JsonDeserializer::class))
        );
        $container->setDefinition(
            ArrayStringHandler::class,
            new Definition(ArrayStringHandler::class)
                ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
        );
        if (interface_exists(EntityManagerInterface::class)) {
            $container->setDefinition(
                EntityIdHandler::class,
                new Definition(EntityIdHandler::class)
                    ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
                    ->setArgument('$entityManager', new Reference(EntityManagerInterface::class))
            );
        }
        $container
            ->registerForAutoconfiguration(HandlerInterface::class)
            ->addTag(SakulbSerializerBundle::TAG_SERIALIZER_HANDLER)
        ;

        $container->setDefinition(
            HandlerResolver::class,
            new Definition(HandlerResolver::class)
        );

        $container->setDefinition(
            self::SERIALIZER_PARAMETER_BAG_ID,
            new Definition(ParameterBag::class)
                ->setArgument('$parameters', $config[Configuration::CONFIG_PARAMETER_BAG])
        );

        $container->setDefinition(
            MetadataFactory::class,
            new Definition(MetadataFactory::class)
                ->setArgument('$parameterBag', new Reference(self::SERIALIZER_PARAMETER_BAG_ID))
        );

        $container->setDefinition(
            MetadataRegistry::class,
            new Definition(MetadataRegistry::class)
                ->setArgument('$appCache', new Reference(CacheItemPoolInterface::class))
                ->setArgument('$appLogger', new Reference(LoggerInterface::class))
                ->setArgument('$metadataFactory', new Reference(MetadataFactory::class))
        );

        $container->setDefinition(
            JsonSerializer::class,
            new Definition(JsonSerializer::class)
                ->setArgument('$handlerResolver', new Reference(HandlerResolver::class))
                ->setArgument('$metadataRegistry', new Reference(MetadataRegistry::class))
        );

        $container->setDefinition(
            JsonDeserializer::class,
            new Definition(JsonDeserializer::class)
                ->setArgument('$handlerResolver', new Reference(HandlerResolver::class))
                ->setArgument('$metadataRegistry', new Reference(MetadataRegistry::class))
        );

        $container->setDefinition(
            Serializer::class,
            new Definition(Serializer::class)
                ->setArgument('$jsonSerializer', new Reference(JsonSerializer::class))
                ->setArgument('$jsonDeserializer', new Reference(JsonDeserializer::class))
        );

        if (interface_exists(ValueResolverInterface::class)) {
            $container
                ->register(SerializerValueResolver::class)
                ->setArgument('$serializer', new Reference(Serializer::class))
                ->addTag('controller.argument_value_resolver', ['priority' => 150]);
        }

        if (interface_exists(ModelDescriberInterface::class)) {
            $container->setDefinition(
                SerializerModelDescriber::class,
                new Definition(SerializerModelDescriber::class)
                    ->setArgument('$metadataRegistry', new Reference(MetadataRegistry::class))
                    ->setArgument('$handlerResolver', new Reference(HandlerResolver::class))
                    ->addTag('nelmio_api_doc.model_describer', ['priority' => 500])
            );
        }
    }
}
