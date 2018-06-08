<?php

namespace Duo\Scan\Command;

use Duo\Scan\foundModuleStream;
use Duo\Scan\Scanner;
use React\EventLoop\Factory;
use Rx\Scheduler;
use Rx\Scheduler\EventLoopScheduler;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class ScanCommand
 *
 * @package Duo\Scan\Command
 */
class ScanCommand extends AbstractCommand
{

    /**
     * {@inheritdoc}
     */


    /** @var Scanner */
    protected $scanner;

    protected $outputArray;

    protected $foundModules;

    protected $amountOfModules;

    protected function configure()
    {
        parent::configure();
        $this->amountOfModules = \count(file('modules.txt'));
        $this->setName('scan');
        $this->addOption('amount', 'a', InputOption::VALUE_REQUIRED,
            'Amount of modules to scan, max: ' . $this->amountOfModules);
        $this->addOption('speed', 's', InputOption::VALUE_REQUIRED,
            'How fast do you want your scan? (between 1 - 50) (but more than 10 get really loud) ');
        $this->addOption('delay', 'd', InputOption::VALUE_OPTIONAL,
            'Delay in milliseconds between each module scan (defaut 0)', 0);
        $this->setDescription('Attempts to find a targets core version and to find the installed modules.');


    }

    protected function proxyWarning(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('proxy')) {
            $this->proxy = $input->getOption('proxy');
        }
        if (!$this->proxy) {
            $output->write('You are not using a proxy, this can harm the anonymity of your scan.');
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue without using a proxy? (y/n)', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Tor would be a good option');
                return false;
            }
        } else {
            $output->writeln("<comment>Using proxy:</comment> <info> $this->proxy </info>");
        }
        $this->outputArray['url'] = $input->getArgument('url');
        $this->outputArray['proxy'] = $this->proxy;
        return true;
    }

    protected function printCoreDrupalVersion(InputInterface $input, OutputInterface $output)
    {

        $message = strtr('The core version of @site is "@version".', [
            '@site' => $input->getArgument('url'),
            '@version' => $this->scanner->getCoreVersion(),
        ]);

        $vulnerabilities = $this->scanner->findSA();
        $output->writeln("<info>$message</info>" . PHP_EOL);

        if (!empty($vulnerabilities)) {
            foreach ($vulnerabilities as $vulnerability) {
                $output->writeln("<comment>   VULNERABILITY FOUND!  </comment><info>  $vulnerability  </info>");
                $output->writeln("");

            }
        }
        $this->outputArray['core'] = $this->scanner->getCoreVersion();
    }


    protected function versionScanWarning(InputInterface $input, OutputInterface $output, $foundModules)
    {
        if ($foundModules) {
            $output->writeln('Do you want to try a version scan on the modules you have found?');
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('version scans are not subtle do you want to continue?  (y/n)', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('You can always use the deepscan option on 1 found module');
                return;
            }
            $this->showModuleVersions($output, $foundModules);
        }
        $output->writeln('None of the scanned modules were found on the target site.');


    }



    protected function showModuleVersions(OutputInterface $output, $modulenames)
    {

        foreach ($modulenames as $modulename) {
            $moduleVersions = $this->scanner->getVersionsOfModule($modulename);
            if (count($moduleVersions) > 0) {
                $output->writeln('<info>The module <comment>' . $modulename . '</comment>can be any of the following versions:</info>'.PHP_EOL);
                foreach ($moduleVersions as $version) {
                    $output->writeln($version);
                }
            }
            else{
                $output->writeln('<info>Could not determine version of: </info><comment>' . $modulename);

            }
        }

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->outputArray = [];
        $this->scanner = new Scanner($input->getArgument('url'), $this->proxy);
        $this->showBanner($output);

        if ($this->proxyWarning($input, $output)) {
            $this->printCoreDrupalVersion($input, $output);
            $loop = Factory::create();
            $this->foundModuleStream($loop, $input, $output);
            $loop->run();
            $this->versionScanWarning($input, $output, $this->foundModules);
            $this->log($this->scanner->getBaseUrl(), $this->outputArray);

        };

    }

    /**
     * @param $loop
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function foundModuleStream($loop, InputInterface $input, OutputInterface $output): void
    {

        Scheduler::setDefaultFactory(function () use ($loop) {
            return new EventLoopScheduler($loop);
        });


        $observable = new FoundModuleStream(
            $loop,
            $input->getArgument('url'),
            $input->getOption('speed'),
            $input->getOption('amount'),
            $output,
            $this->scanner
        );

        $next = function ($module) use ($output, $observable) {
            $this->foundModules[] = $module;
            echo 'NEXT';
        };

        $fail = function ($reason) use ($output) {
            $output->writeln($reason);

        };

        $completed = function () use ($output) {
            $foundModulesNumber = \count($this->foundModules);

            $output->writeln(PHP_EOL . '<info>Scan Completed </info>');
            $output->writeln(PHP_EOL . "<comment>These $foundModulesNumber modules were found: </comment>" . PHP_EOL);

            foreach ($this->foundModules as $module) {
                $output->writeln("<info>$module</info>");
            }

            $this->outputArray["modules"] = $this->foundModules;
        };

        $observable->subscribe($next, $fail, $completed);

    }

}








