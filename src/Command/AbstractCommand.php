<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 23/02/2018
 * Time: 11:47
 */

namespace Duo\Scan\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractCommand
 * @package Duo\Scan\Command
 */
abstract class AbstractCommand extends Command
{
    protected $proxy;


    protected function configure()
    {
        $this->addOption('proxy', null, InputOption::VALUE_OPTIONAL, 'SOCKS5 proxy');
        $this->addArgument('url', InputArgument::REQUIRED);
        $this->proxy = $this->getConfig("proxy");
    }

    protected function getConfig($configName)
    {

        $file = file_get_contents('src/config.json');
        $json = json_decode($file, true);

        if ($json[$configName]) {
            return $json[$configName];
        }
        return "";
    }

    protected function setConfig($key, $val)
    {

        $file = file_get_contents('src/config.json');
        $json = json_decode($file, true);
        $json[$key] = $val;
        $json = json_encode($json);
        file_put_contents('src/config.json', $json);
    }

    protected function log($url, $data)
    {
        $fileName = strtr('results/@url@time.json', [
            '@url' => $url,
            '@time' => time(),
        ]);

        $json = json_encode($data);
        fopen($fileName, 'wb');
        file_put_contents($fileName, $json);


    }

    protected function showBanner(OutputInterface $output){
        $output->write('
     <fg=green>
     _                  _                   _
  __| |_ __ _   _ _ __ | | ____      ____ _| |
 / _` | \'__| | | | \'_ \| |/ /\ \ /\ / / _` | |
| (_| | |  | |_| | |_) |   <  \ V  V / (_| | |
 \__,_|_|   \__,_| .__/|_|\_\  \_/\_/ \__,_|_|
                 |_|
            </>     
');
        $output->writeln('');
    }

}
