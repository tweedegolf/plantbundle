<?php

namespace TweedeGolf\PlantBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Elastica\Client;
use \Elastica\Document;
use TweedeGolf\PlantBundle\Retriever\PlantRetriever;

/**
 * Command for updating the tweedegolf 'plant' search index
 *
 * Class ElasticaCommand
 * @package TweedeGolf\PlantBundle\Command
 */
class ElasticaCommand extends ContainerAwareCommand
{
    private $derivedProperties = [
        'edible',
        'sustainable'
    ];

    protected function configure()
    {
        $this
            ->setName('elastica:refresh')
            ->setDescription('Refresh the index');
    }

    protected function createIndex($index)
    {
        // Create the index new
        $index->create(
            array(
                'number_of_shards' => 4,
                'number_of_replicas' => 1,
                'analysis' => array(
                    'analyzer' => array(
                        'indexAnalyzer' => array(
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => array('lowercase', 'mySnowball')
                        ),
                        'searchAnalyzer' => array(
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => array('standard', 'lowercase', 'mySnowball')
                        )
                    ),
                    'filter' => array(
                        'mySnowball' => array(
                            'type' => 'snowball',
                            'language' => 'Dutch'
                        )
                    )
                )
            ),
            true
        );
    }

    /* Execute: what happens when the command is executed */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var PlantRetriever $retriever */
        $retriever = $this->getContainer()->get('tweedegolf_plant.plant_retriever');
        $progress = $this->getHelperSet()->get('progress');

        $port = $this->getContainer()->getParameter('tweedegolf_plant.elastica_port');
        $host = $this->getContainer()->getParameter('tweedegolf_plant.elastica_host');

        /* Elastica */
        $client = new Client(['host' => $host, 'port' => $port]);
        $index = $client->getIndex('plant');
        $index->delete();
        $this->createIndex($index);
        $type = $index->getType('plant');

        $plantCount = $retriever->getPlantCount();
        $progress->start($output, $plantCount);

        $languages = $this->getContainer()->getParameter('languages');

        $j = 0;
        foreach($languages as $locale => $label) {
            for ($i = 0; $i < $plantCount; $i += 100) {
                $plants = $retriever->getLimitedPlants(100, $i, $locale);

                foreach ($plants as $properties) {

                    if (count($properties) > 0) {

                        /* Fill a dummy entity with names, use */
                        $document = [];
                        $document['plantid'] = $properties[0]['plant_id'][0];
                        $document['id'] = $j;
                        $document['name'] = json_decode($properties[0]['names']);
                        $document['locale'] = $locale;

                        // set properties that have a value based only on their own 'values' key only
                        foreach ($properties as $prop) {
                            if (!in_array($prop['name'], $this->derivedProperties)) {
                                $document[$prop['name']] = json_decode($prop['values']);
                            }
                        }

                        // set derived properties
                        $document['edible'] = $this->getEdibility($properties);
                        $document['sustainable'] = $this->getSustainable($properties);

                        $doc = new Document($j, $document);
                        $type->addDocument($doc);
                        $type->getIndex()->refresh();
                        $progress->advance();
                        $j += 1;
                    }
                }
            }
        }

        $progress->finish();
    }

    /**
     * @param $properties
     * @return bool
     */
    private function getEdibility($properties)
    {
        if (!isset($properties['fruit'])) {
            return false;
        }

        $values = $properties['fruit'];

        return in_array('edible', $values) || in_array('unusual taste', $values);
    }

    /**
     * @param $properties
     * @return bool
     */
    private function getSustainable($properties)
    {
        if (!isset($properties['use'])) {
            return false;
        }

        $use = $properties['use'];

        return in_array('butterfly host plant', $use) || in_array('bee plant', $use);
    }
}
