<?php

namespace TweedeGolf\PlantBundle\Search;

use \Elastica\Client;
use \Elastica\Search;
use \Elastica\Query;
use \Elastica\Query\Match;
use \Elastica\Query\Bool;

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
     * @var Name of the plant search index
     */
    private $index;

    /**
     * @param PlantTransformer $transformer
     * @param $host
     * @param $port
     * @param $plantIndex
     */
    public function __construct(PlantTransformer $transformer, $host, $port, $plantIndex)
    {
        $this->client = new Client(['host' => $host, 'port' => $port]);
        $this->search = new Search($this->client);
        $this->transformer = $transformer;
        $this->index = $plantIndex;
        $this->searchable = $this->client->getIndex($this->index);
    }

    /**
     * @param $q
     * @param $locale is verplicht
     * @return mixed
     */
    public function search(
        $q /* Geen lege queries */,
        $locale /* Geen default! */,
        $options = null
    )
    {
        $this->search->addIndex($this->index);
        $this->search->addType('plant');

        /* Locale */
        $locale_check = new Match();
        $locale_check -> setField("locale", $locale);

        /* The received query */
        $query = new Query($q);        

        /* Tie both queries together */
        $bool = new Bool();
        $bool ->addShould($q);
        $bool ->addShould($locale_check);

        $this->search->setQuery($bool);

        return $this->search->search($q, $options);
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
