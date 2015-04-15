<?php


namespace TweedeGolf\PlantBundle\Search;

use FOS\ElasticaBundle\Transformer\ElasticaToModelTransformerInterface;

/**
 * Custom transformer needed for the findPaginated call in the PlantFinder
 *
 * Class PlantTransformer
 * @package Tjt\MainBundle\Retriever
 */
class PlantTransformer implements ElasticaToModelTransformerInterface
{
    /**
     * Only get the ids form the search results
     * @param array $elasticaObjects
     * @return array
     */
    function transform(array $elasticaObjects)
    {
        $ids = [];
        /** @var \Elastica\Result $obj */
        foreach($elasticaObjects as $obj) {
            $ids[] = $obj->getSource()['plantid'];
        }

        return $ids;

    }

    /**
     * Not used
     *
     * @param array $elasticaObjects
     * @return array
     */
    function hybridTransform(array $elasticaObjects)
    {
        return [];
    }

    /**
     * Not used
     *
     * @return string
     */
    function getObjectClass()
    {
        return '';
    }

    /**
     * Not used
     *
     * @return string the identifier field
     */
    function getIdentifierField()
    {
        return '';
    }
}