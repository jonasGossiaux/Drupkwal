<?php

namespace Duo\Scan\Command;


use Duo\Scan\Scanner;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class GetBadModulesCommand
 * @package Duo\Scan\Command
 */
class DeepScanModuleCommand extends AbstractCommand
{


    protected function configure()
    {
        parent::configure();
        $this->setName('ds');
        $this->addArgument('moduleName', null, InputOption::VALUE_REQUIRED, 'Name of the module you want to scan');
        $this->setDescription('Attempts to find the version of the given module.');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $modulename = $input->getArgument('moduleName');
        $scanner = new Scanner($input->getArgument('url'), $this->proxy);
        $moduleVersions = $scanner->getVersionsOfModule($modulename);
        $this->printModuleVersions($moduleVersions, $output);
        $output->writeln("using proxy: $this->proxy");
    }



    protected function printModuleVersions($moduleVersions, OutputInterface $output)
    {

        if (count($moduleVersions) > 0) {
            $output->writeln('this module can be any of the following versions: ');
            foreach ($moduleVersions as $moduleVersion) {
                $output->writeln($moduleVersion);
            }
        } else {
            $output->writeln('Unable to determine the version of this module ');
        }
    }
}
