<?php

declare(strict_types=1);

namespace Sakulb\SerializerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'sakulb_serializer';
    public const string CONFIG_DATE_FORMAT = 'date_format';
    public const string CONFIG_PARAMETER_BAG = 'parameter_bag';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::ALIAS);
        $tree->getRootNode()
            ->children()
                ->scalarNode(self::CONFIG_DATE_FORMAT)->defaultValue('Y-m-d\TH:i:s.u\Z')->end()
                ->arrayNode(self::CONFIG_PARAMETER_BAG)->scalarPrototype()->end()
            ->end()
        ;

        return $tree;
    }
}
