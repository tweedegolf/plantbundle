<?php

namespace TweedeGolf\PlantBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tweede_golf_plant');

        $rootNode
            ->children()
                ->scalarNode('elastica_host')->defaultValue('127.0.0.1')->end()
                ->scalarNode('elastica_port')->defaultValue('9200')->end()
            ->end()
        ;
        
        return $treeBuilder;

    }
}
