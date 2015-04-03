<?php

namespace TweedeGolf\PlantBundle\Search;

use \Elastica\Client;
use \Elastica\Search;
use \Elastica\Query;
use FOS\ElasticaBundle\Paginator\TransformedPaginatorAdapter;

/**
 * Custom finder to search the plant database. It provides a search method and a
 * findPaginated mathod
 *
 * Class PlantFinder
 * @package TweedeGolf\PlantBundle\Search
 */
class PlantFinder
{
    /**
     * @param PlantTransformer $transformer
     * @param $host
     * @param $port
     */
    public function __construct(PlantTransformer $transformer, $host, $port)
    {
        $this->client = new Client(['host' => $host, 'port' => $port]);
        $this->search = new Search($this->client);
        $this->transformer = $transformer;
        $this->searchable = $this->client->getIndex('plant');
    }

    /**
     * @param $q
     * @return mixed
     */
    public function search($q)
    {
        $this->search->addIndex('plant');
        $this->search->addType('plant');

        $query = new Query($q);
        $this->search->setQuery($query);

        return $this->search->search();
    }

    /**
     * @param $query
     * @param $offset
     * @param $limit
     * @return \FOS\ElasticaBundle\Paginator\PartialResultsInterface|\FOS\ElasticaBundle\Paginator\TransformedPartialResults
     */
    public function findPaginated($query, $offset, $limit)
    {
        $queryObject = Query::create($query);
        $paginatorAdapter = $this->createPaginatorAdapter($queryObject, []);

        return $paginatorAdapter->getResults($offset, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function createPaginatorAdapter($query, $options = array())
    {
        $query = Query::create($query);

        return new TransformedPaginatorAdapter($this->searchable, $query, $options, $this->transformer);
    }
}
