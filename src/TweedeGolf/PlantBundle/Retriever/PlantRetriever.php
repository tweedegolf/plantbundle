<?php

namespace TweedeGolf\PlantBundle\Retriever;

use Doctrine\DBAL\Connection;

/**
 * The PlantRetriever
 * 
 * is designed to serve as a repository for functions that query the dbtools' 
 * plants. There should be no queries on that db except here.
 * 
 * Note: this class returns plantproxies
 */
class PlantRetriever
{
    /* The DBAL database connection */
    protected $connection;

    /**
     * Constructor
     */ 
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find one plant by the given id and an optional locale
     * Returns either null or a PlantProxy
     */
    public function getPlantById($id, $locale = 'en')
    {
        $sql = "
            SELECT *
            FROM public.plant plant, public.property prop
            WHERE plant.id=?
              AND prop.plant_id=plant.id;
        ";

        $query = $this->connection->prepare($sql);
        $query->bindValue(1, $id);
        $query->execute();

        $properties = $query->fetchAll();
        if (count($properties) < 1) {
            return null;
        }

        /* Transform to Proxy */
        $proxy = $this->propertiesToProxy($id, $properties, $locale);

        return $proxy;
    }

    /**
     * Find an array of plants with the given array of ids 
     * and an optional locale. Returns either [] or an array of PlantProxy
     */
    public function getPlantsById($ids, $locale = 'en')
    {
        $sql = "
            SELECT *
            FROM public.plant plant, public.property prop
            WHERE plant.id IN (?)
              AND prop.plant_id=plant.id
              AND prop.locale='en';
        ";

        $query = $this->connection->executeQuery(
            $sql,
            array($ids),
            array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
        );

        $properties = $query->fetchAll();
        if (count($properties) < 1) {
            return [];
        }

        $plants = [];
        foreach ($properties as $p) {
            $plants[$p['plant_id']][] = $p;
        }

        $results = [];
        foreach ($plants as $id => $properties) {
            $results[] = $this->propertiesToProxy($id, $properties, $locale);
        }

        return $results;
    }

    /**
     * Return the total count of plants in the database
     */
    public function getPlantCount()
    {
        $sql = "
                SELECT count(*)
                FROM public.plant plant;
        ";
        $query = $this->connection->executeQuery($sql);
        $count = $query->fetchAll()[0]['count'];

        return $count;
    }

    /**
     * Return the total count of plants in the database
     */
    public function getLimitedPlants($limit = 100, $offset = 0)
    {
        $sql = "
            SELECT *
            FROM public.plant plant
            LIMIT ? OFFSET ?;
        ";

        $query = $this->connection->prepare($sql);
        $query->bindValue(1, $limit);
        $query->bindValue(2, $offset);        
        $query->execute();
        $plants = $query->fetchAll();

        $results = [];
        foreach ($plants as $plant) {

            $sql = "
                SELECT *
                FROM public.plant plant, public.property prop
                WHERE plant.id=?
                  AND prop.plant_id=plant.id;
            ";

            $query = $this->connection->prepare($sql);
            $query->bindValue(1, $plant['id']);
            $query->execute();

            $results[] = $query->fetchAll();
        }

        return $results;
    }

    /**
     * Protected function that converts a list of properties from the database 
     * into a plantproxy
     */
    protected function propertiesToProxy($id, $properties, $locale = 'en')
    {
        $proxy = new PlantProxy($id);

        foreach ($properties as $property) {
            if ($property['locale'] === $locale) {
                $proxy->set(
                    $property['name'],
                    json_decode($property['values']),
                    true,
                    $property['type']
                );
            }
        }

        // set some properties separately
        $props = $properties[0];
        $proxy->setCreatedAt($props['createdat']);
        $proxy->setUpdatedAt($props['updatedat']);
        $proxy->set('names', unserialize($props['names']), true, 'lines');

        return $proxy;
    }
}