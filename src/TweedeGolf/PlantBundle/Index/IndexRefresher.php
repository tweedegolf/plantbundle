<?php

namespace TweedeGolf\PlantBundle\Index;

use Symfony\Component\Console\Output\OutputInterface;
use \Elastica\Client;
use \Elastica\Document;
use \Elastica\Index;
use TweedeGolf\PlantBundle\Retriever\PlantRetriever;

/**
 * Service containing one public method to refresh the plant index. Mainly defined to be called from the ElasticaCommand
 * command and to allow refreshing the index when testing in the tekenjetuin project
 *
 * Class IndexRefresher
 * @package TweedeGolf\PlantBundle\Index
 */
class IndexRefresher
{
    /**
     * @var array
     */
    private $derivedProperties = [
        'edible',
        'sustainable'
    ];

    /**
     * @var PlantRetriever
     */
    private $plantRetriever;

    /**
     * @var string
     */
    private $elasticaPort;

    /**
     * @var string
     */
    private $elasticaHost;

    /**
     * @var Array
     */
    private $propertyTranslations;

    /**
     * @var string Name of the plant index
     */
    private $plantIndex;

    /**
     * @var Array the languages we have within the app
     */
    private $languages;

    /**
     * @param PlantRetriever $retriever
     * @param $elasticaPort
     * @param $elasticaHost
     */
    public function __construct(
        PlantRetriever $retriever,
        $elasticaPort,
        $elasticaHost,
        $propertyTranslations,
        $plantIndex,
        $languages
    ) {
        $this->plantRetriever = $retriever;
        $this->elasticaPort = $elasticaPort;
        $this->elasticaHost = $elasticaHost;
        $this->propertyTranslations = $propertyTranslations;
        $this->plantIndex = $plantIndex;
        $this->languages = $languages;
    }

    /**
     * The refresh method. Accepts an option OutputInterface and $progress variable for usage from within
     * a command such as the ElasticaRefresh command
     *
     * @param OutputInterface $output
     * @param null $progress
     */
    public function refresh(OutputInterface $output = null, $progress = null)
    {
        $client = new Client(['host' => $this->elasticaHost, 'port' => $this->elasticaPort]);
        $index = $client->getIndex($this->plantIndex);

        if ($index->exists()) {
            $index->delete();
        }

        $this->createIndex($index);
        $type = $index->getType('plant');

        $mapping = $this->getMapping();

        // explicitly set mapping do define analyzer
        $type->setMapping($mapping);

        $j = 0;
        foreach($this->languages as $locale => $label) {

            $plantCount = $this->plantRetriever->getPlantCount($locale);
            if ($output) {
                $output->writeln('Count: '.$plantCount);
                $output->writeln('Indexing: '.$label);
            }

            if ($progress) {
                $progress->start($output, $plantCount);
            }

            for ($i = 0; $i < $plantCount; $i += 100) {
                $plants = $this->plantRetriever->getLimitedPlants(100, $i, $locale);

                foreach ($plants as $info) {
                    $properties = $info['properties'];
                    $plant = $info['plant'];

                    /* Fill a dummy entity with names, use */
                    $document = [];
                    $document['plantid'] = $plant['id'];
                    $document['id'] = $j;
                    $document['names'] = json_decode($plant['names']);
                    $document['locale'] = $locale;
                    $document['identifier'] = $plant['identifier'];

                    if (count(unserialize($plant['images'])) > 0) {
                        $document['images'] = true;
                    }

                    if (count($properties) > 0) {
                        // set properties that have a value based only on their own 'values' key only
                        foreach ($properties as $prop) {
                            if (!in_array($prop['name'], $this->derivedProperties)) {
                                $data = json_decode($prop['values']);
                                if (is_array($data) && count($data) === 1) {
                                    $data = $data[0];
                                }

                                if (!isset($document[$prop['name']])) {
                                    $document[$prop['name']] = [];
                                }

                                if (!is_array($document[$prop['name']])) {
                                    continue;
                                }

                                $document[$prop['name']][] = $data;
                            }
                        }

                        // set derived properties
                        $document['plantid'] = $properties[0]['plant_id'];
                        $document[$this->getEdibleProperty($locale)] = $this->getEdibility($properties, $locale);
                        $document[$this->getSustainableProperty($locale)] = $this->getSustainable($properties, $locale);
                    }

                    $doc = new Document($j, $document);
                    $type->addDocument($doc);
                    $type->getIndex()->refresh();
                    if ($progress) {
                        $progress->advance();
                    }

                    $j += 1;
                }
            }
            if ($progress) {
                $progress->finish();
            }
        }
    }

    /**
     * Create the plant index with a set of parameters.
     * @param Index $index
     */
    protected function createIndex(Index $index)
    {
        // Create the index new
        $index->create([
            'analysis' => [
                'analyzer' => [
                    'plant_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'plant_ngram',
                        'filter' => ['lowercase'],
                    ],
                ],
                'tokenizer' => [
                    'plant_ngram' => [
                        'type' => 'nGram',
                        'min_gram' => 2,
                        'max_gram' => 3,
                    ]
                ]
            ]
        ], true);
    }

    /**
     * Construct mapping for the text searchable properties that explicity sets
     * the analyzer that is to be used
     *
     * @return array
     */
    private function getMapping()
    {
        $mapping = [];
        $propertyTranslations = $this->propertyTranslations;
        $set = function ($property, $settings) use ($propertyTranslations, &$mapping) {
            foreach ($propertyTranslations[$property] as $locale => $prop) {
                $mapping[$prop] = $settings;
            }
        };

        $analyzedMapping = ['type' => 'string', 'analyzer' => 'plant_analyzer'];
        $termMapping = ['type' => 'string', 'index' => 'not_analyzed'];

        $mapping['names'] = $analyzedMapping;
        $set('use', $analyzedMapping);
        $set('common_name', $analyzedMapping);
        $set('flower', $termMapping);
        $set('flowering_season', $termMapping);
        $set('type_of_plant', $termMapping);
        $mapping['locale'] = $termMapping;

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
}