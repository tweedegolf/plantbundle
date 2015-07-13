<?php

namespace TweedeGolf\PlantBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for updating the tweedegolf 'plant' search index
 *
 * Class ElasticaCommand
 * @package TweedeGolf\PlantBundle\Command
 */
class ElasticaCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('elastica:refresh')
            ->setDescription('Refresh the index');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $progress = $this->getHelperSet()->get('progress');
        $refresher = $this->getContainer()->get('tweedegolf_plant.index_refresher');
        $refresher->refresh($output, $progress);
    }
}
