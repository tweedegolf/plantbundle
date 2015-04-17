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

        $languages = $this->getContainer()->getParameter('languages');

        $j = 0;
        foreach($languages as $locale => $label) {

            $plantCount = $retriever->getPlantCount($locale);
            $output->writeln('Count: '.$plantCount);
            $output->writeln('Indexing: '.$label);
            $progress->start($output, $plantCount);

            for ($i = 0; $i < $plantCount; $i += 100) {
                $plants = $retriever->getLimitedPlants(100, $i, $locale);

                foreach ($plants as $properties) {
                    if (count($properties) > 0) {

                        /* Fill a dummy entity with names, use */
                        $document = [];
                        $document['id'] = $j;
                        $document['name'] = json_decode($properties[0]['names']);
                        $document['locale'] = $locale;

                        // set properties that have a value based only on their own 'values' key only
                        foreach ($properties as $prop) {
                            if (!in_array($prop['name'], $this->derivedProperties)) {
                                $document[$prop['name']][] = json_decode($prop['values']);
                            }
                        }

                        // set derived properties
                        $document['plantid'] = $properties[0]['plant_id'];
                        $document[$this->getEdibleProperty($locale)] = $this->getEdibility($properties, $locale);
                        $document[$this->getSustainableProperty($locale)] = $this->getSustainable($properties, $locale);

                        $doc = new Document($j, $document);
                        $type->addDocument($doc);
                        $type->getIndex()->refresh();
                        $progress->advance();
                        $j += 1;
                    }
                }
            }
            $progress->finish();
        }
    }

    private function getEdibleProperty($locale)
    {
        if ($locale === 'nl') {
            return 'eetbaar';
        } elseif ($locale === 'de') {
            return 'essbar';
        } elseif ($locale === 'fr') {
            return 'comestible';
        } else {
            return 'edible';
        }
    }

    private function getSustainableProperty($locale)
    {
        if ($locale === 'nl') {
            return 'duurzaam';
        } elseif ($locale === 'de') {
            return 'nachhaltiger';
        } elseif ($locale === 'fr') {
            return 'durable';
        } else {
            return 'sustainable';
        }
    }

    /**
     * Check the 'edible' property for each locale
     *
     * @param $properties
     * @return bool
     */
    private function getEdibility($properties, $locale)
    {
        switch($locale) {
            case 'nl':
                if (!isset($properties['vrucht'])) {
                    return false;
                }

                return in_array('eetbaar', $properties['vrucht']) || in_array('aparte smaak', $properties['vrucht']);
            case 'fr':
                if (!isset($properties['fruit'])) {
                    return false;
                }

                return in_array('comestible', $properties['fruit']);
            case 'de':
                if (!isset($properties['frucht'])) {
                    return false;
                }

                return in_array('essbar', $properties['frucht']);
            default:
                if (!isset($properties['fruit'])) {
                    return false;
                }

                return in_array('edible', $properties['fruit']) || in_array('unusual taste', $properties['fruit']);
        }
    }

    /**
     * Check how sustainable the plant is for all languages, rather arbitrary definition...
     *
     * @param $properties
     * @return bool
     */
    private function getSustainable($properties, $locale)
    {
        switch($locale) {
            case 'nl':
                return isset($properties['gebruik']) && (in_array('waardplant voor vlinders', $properties['gebruik']) || in_array('bijenplant', $properties['gebruik']));
            case 'fr':
                return isset($properties['utilisation']) && (in_array('hôte pour les papillons', $properties['utilisation']));
            case 'de':
                return isset($properties['verwendung']) && (in_array('Wirtpflanze für Schmetterlinge', $properties['verwendung']) || in_array('Bienenpflanze', $properties['verwendung']));
            default:
                return isset($properties['use']) && (in_array('butterfly host plant', $properties['use']) || in_array('bee plant', $properties['use']));
        }
    }
}
