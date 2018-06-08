<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 23/02/2018
 * Time: 14:00
 */


namespace Duo\Scan\Command;

use function PHPSTORM_META\type;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class UpdateModuleListCommand extends AbstractCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('um');
        $this->setDescription('Updates the module list, do this before you start scanning');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $json = file_get_contents('https://packages.drupal.org/8/packages.json');
        $json = json_decode($json);
        $urls = array();
        $fp = fopen('modules.txt', 'w');
        $provFile = fopen('providerLinks.txt', 'w');

        foreach ($json->{'provider-includes'} as $baseUrl => $hash) {

            $url = str_replace('%hash%', $hash->sha256, $baseUrl);
            $quarterJson = json_decode(file_get_contents('https://packages.drupal.org/8/' . $url));

            foreach ($quarterJson->{'providers'} as $module => $meta) {
                fwrite($fp, str_replace('drupal/', '', $module) . PHP_EOL);
            }
            $urls[] = $url;
        }

        fclose($fp);
    }

}














