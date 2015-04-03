<?php


namespace TweedeGolf\PlantBundle\Retriever;

use Doctrine\DBAL\Connection;

/**
 * Class PropertyRetriever
 * @package TweedeGolf\PlantBundle\Retriever
 */
class PropertyRetriever
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param $name
     * @return array|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPropertyByName($name)
    {
        // dirty casting to text but works
        $sql = "
            SELECT DISTINCT p.values::text
            FROM public.property p
            WHERE p.name = ?
        ";

        $query = $this->connection->prepare($sql);
        $query->bindParam(1, $name);
        $query->execute();

        $result = $query->fetchAll();

        if (count($result) === 0) {
            return null;
        }

        // construct property info to return
        $property = [
            'name' => $name,
            'distinct_values' => []
        ];

        foreach($result as $data) {
            $v = json_decode($data['values']);

            foreach($v as $value) {
                if (!in_array($value, $property['distinct_values'])) {
                    $property['distinct_values'][] = $value;
                }
            }
        }

        return $property;
    }
}