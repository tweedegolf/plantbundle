<?php

namespace TweedeGolf\PlantBundle\Retriever;

use Doctrine\DBAL\Connection;
use Symfony\Component\Translation\TranslatorInterface;

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
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Constructor
     */ 
    public function __construct(Connection $connection, TranslatorInterface $translator)
    {
        $this->connection = $connection;
        $this->translator = $translator;
    }

    /**
     * Find one plant by the given id and an optional locale that overrides $locale
     * Returns either null or a PlantProxy
     */
    public function getPlantById($id, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->translator->getLocale();

        $sql = "
            SELECT *
            FROM public.plant plant, public.property prop
            WHERE plant.id = ?
              AND prop.plant_id=plant.id
              AND prop.locale = ?
        ";

        $query = $this->connection->prepare($sql);
        $query->bindValue(1, $id);
        $query->bindValue(2, $locale);
        $query->execute();

        $properties = $query->fetchAll();
        if (count($properties) < 1) {
            return null;
        }

        /* Transform to Proxy */
        $proxy = $this->propertiesToProxy($id, $properties, $locale, $properties[0]['identifier']);

        return $proxy;
    }

    /**
     * Find one plant by the given identifier (hash) and an optional locale that overrides $this->locale
     * Returns either null or a PlantProxy
     */
    public function getPlantByIdentifier($identifier, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->locale;

        $sql = "
            SELECT *
            FROM public.plant plant, public.property prop
            WHERE plant.identifier=?
              AND prop.plant_id=plant.id
              AND prop.locale =?
        ";

        $query = $this->connection->prepare($sql);
        $query->bindValue(1, $identifier);
        $query->bindValue(2, $locale);
        $query->execute();

        $properties = $query->fetchAll();
        if (count($properties) < 1) {
            return null;
        }

        /* Transform to Proxy */
        $proxy = $this->propertiesToProxy($identifier, $properties, $locale, $identifier);

        return $proxy;
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
     * Return the total count of plants in the database, needed for the ElasticaCommmand
     */
    public function getLimitedPlants($limit = 100, $offset = 0, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->translator->getLocale();

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
                WHERE plant.id = ?
                  AND prop.plant_id=plant.id
                  AND prop.locale = ?
                  ;
            ";
            $query = $this->connection->prepare($sql);
            $query->bindValue(1, $plant['id']);
            $query->bindValue(2, $locale);
            $query->execute();
            $results[] = ['properties' => $query->fetchAll(), 'plant' => $plant];
        }
        return $results;
    }

    /**
     * Find an array of plants with the given array of ids 
     * and an optional locale. Returns either [] or an array of PlantProxy
     */
    public function getPlantsById(array $ids, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->translator->getLocale();

        $sql = "
            SELECT *
            FROM public.plant plant, public.property prop
            WHERE plant.id IN (?)
              AND prop.plant_id = plant.id
              AND prop.locale = ?;
        ";

        $query = $this->connection->executeQuery(
            $sql,
            array($ids, $locale),
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
            $results[] = $this->propertiesToProxy($id, $properties, $locale, $properties[0]['identifier']);
        }

        usort($results, function ($a, $b) use ($ids) {
            $idx_a = array_search($a->getId(), $ids);
            $idx_b = array_search($b->getId(), $ids);
            if ($idx_a === $idx_b) {
                return 0;
            }

            return ($idx_a < $idx_b) ? -1 : 1;
        });

        return $results;
    }
    
    /**
     * Protected function that converts a list of properties from the database 
     * into a PlantProxy
     */
    protected function propertiesToProxy($id, $properties, $locale = null, $identifier = null)
    {
        $locale = $locale !== null ? $locale : $this->translator->getLocale();

        $proxy = new PlantProxy($id);

        foreach ($properties as $property) {
            if ($property['locale'] === $locale) {
                $proxy->set(
                    $property['name'],
                    json_decode($property['values']),
                    false,
                    $property['type']
                );
            }
        }

        // set some properties separately
        $props = $properties[0];
        $proxy->setCreatedAt($props['createdat']);
        $proxy->setUpdatedAt($props['updatedat']);
        $proxy->set('names', json_decode($props['names']), true, 'lines');
        $proxy->set('images', unserialize($props['images']), true, 'images');
        $proxy->set('identifier', $identifier);
        $proxy->setIdentifier($identifier);

        return $proxy;
    }
}
