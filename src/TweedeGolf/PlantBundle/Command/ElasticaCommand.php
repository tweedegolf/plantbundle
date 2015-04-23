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
                'analysis' => array(
                    'analyzer' => array(
                        'indexAnalyzer' => array(
                            'type' => 'custom',
                            'tokenizer' => 'plant_ngram',
                            'filter' => array('standard', 'lowercase')
                        ),
                        'searchAnalyzer' => array(
                            'type' => 'custom',
                            'tokenizer' => 'plant_ngram',
                            'filter' => array('standard', 'lowercase')
                        )
                    ),
                    'tokenizer' => array(
                        'plant_ngram' => array(
                            'type' => 'nGram',
                            'min_gram' => '2',
                            'max_gram' => '3'
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

        if ($index) {
            $index->delete();
        }

        $this->createIndex($index);
        $type = $index->getType('plant');

        $mapping = $this->getMapping();

        // explicitly set mapping do define analyzer
        $type->setMapping($mapping);

        $languages = $this->getContainer()->getParameter('languages');

        $j = 0;
        foreach($languages as $locale => $label) {

            $plantCount = $retriever->getPlantCount($locale);
            $output->writeln('Count: '.$plantCount);
            $output->writeln('Indexing: '.$label);
            $progress->start($output, $plantCount);

            for ($i = 0; $i < $plantCount; $i += 100) {
                $plants = $retriever->getLimitedPlants(100, $i, $locale);

                foreach ($plants as $info) {
                    $properties = $info['properties'];
                    $plant = $info['plant'];

                    if (count($properties) > 0) {

                        /* Fill a dummy entity with names, use */
                        $document = [];
                        $document['id'] = $j;
                        $document['name'] = json_decode($properties[0]['names']);
                        $document['identifier'] = $this->getUniqueIdentifier($properties[0]['names']);
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

                    } else {
                        /* Fill a dummy entity with names, use */
                        $document = [];
                        $document['plantid'] = $plant['id'];
                        $document['id'] = $j;
                        $document['name'] = json_decode($plant['names']);
                        $document['locale'] = $locale;
                        $document['images'] = unserialize($plant['images']);
                    }

                    $doc = new Document($j, $document);
                    $type->addDocument($doc);
                    $type->getIndex()->refresh();
                    $progress->advance();
                    $j += 1;

                }
            }
            $progress->finish();
        }
    }

    /**
     * Construct mapping for the text searchable properties that explicity sets
     * the analyzer that is to be used
     *
     * @return array
     */
    private function getMapping()
    {
        $props = [
            'use',
            'names',
            'common_name',
            'flower',
            'flowering_season',
            'type_of_plant',
        ];

        $propertyTranslations = $this->getContainer()->getParameter('plant_properties');

        $mapping = [];

        foreach($props as $name) {
            foreach($propertyTranslations[$name] as $locale => $prop)
            $mapping[$prop] = ['type' => 'string', 'analyzer' => 'searchAnalyzer'];
        }

        return $mapping;
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
        foreach($properties as $property) {
            if(in_array($property['name'], ['vrucht', 'fruit', 'frucht'])) {
                $property['values'] = json_decode($property['values']);
                switch($locale) {
                    case 'nl':
                        return in_array('eetbaar', $property['values']) || in_array('aparte smaak', $property['values']);
                    case 'fr':
                        return in_array('comestible', $property['values']);
                    case 'de':
                        return in_array('essbar', $property['values']);
                    default:
                        return in_array('edible', $property['values']) || in_array('unusual taste', $property['values']);
                }
            }
        }

        return false;
    }

    /**
     * Check how sustainable the plant is for all languages, rather arbitrary definition...
     *
     * @param $properties
     * @return bool
     */
    private function getSustainable($properties, $locale)
    {
        foreach($properties as $property) {
            if(in_array($property['name'], ['gebruik', 'utilisation', 'verwendung', 'use'])) {
                $property['values'] = json_decode($property['values']);
                switch($locale) {
                    case 'nl':
                        return in_array('waardplant voor vlinders', $property['values']) || in_array('bijenplant', $property['values']);
                    case 'fr':
                        return in_array('hôte pour les papillons', $property['values']);
                    case 'de':
                        return in_array('Wirtpflanze für Schmetterlinge', $property['values']) || in_array('Bienenpflanze', $property['values']);
                    default:
                        return in_array('butterfly host plant', $property['values']) || in_array('bee plant', $property['values']);
                }
            }
        }

        return false;
    }

    /**
     * Get a unique identifier based on the Latin names of the plant
     *
     * @param $names
     * @return string
     */
    private function getUniqueIdentifier($names)
    {
        return substr(base64_encode(hash('sha256', $names)), 0, 10);
    }
}
