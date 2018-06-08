<?php

namespace Duo\Scan;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Request;
use http\Exception;
use Psr\Http\Message\ResponseInterface;
use SebastianBergmann\Diff\Differ;
use function Sodium\add;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\HandlerStack;

/**
 * Class Scanner
 *
 * @package Duo\Scan\Command
 */
class Scanner
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var string
     */
    public $baseModuleUrl;


    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Scanner constructor.
     */
    public function __construct(string $baseUri, string $proxy = null)
    {
        $this->client = new Client([
            'verify' => false,
            'base_uri' => $baseUri,
            'headers' => [
                'User-Agent' =>
                    'Mozilla/5.0 (Linux; Android 6.0.1;
                 Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko)
                 Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Googlebot/2.1;+http://www.google.com/bot.html)',
            ],
            "proxy" => $proxy
        ]);

        $this->baseModuleUrl = $this->findBaseModuleUrl();


    }

    /**
     * @return null|string
     */
    public function getCoreVersion(): ?string
    {
        $version = null;

        $urls = [
            'CHANGELOG.txt',
            'core/CHANGELOG.txt',
            "sites/{$this->client->getConfig('base_uri')}/CHANGELOG.txt",
        ];

        foreach ($urls as $url) {
            $response = $this->client->get($url, [
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 200) {
                $version = $response->getBody()->read(12);
                $version = explode(' ', $version)[1];
                if ($version === 'minor') {
                    $version = '8.5 or newer';
                }
                break;
            }
        }

        return $version;
    }

    public function findSA()
    {
        $coreversion = $this->getCoreVersion();
        $coreversion = substr($coreversion,0,4);
        $allSa = $this->getVersionsAndTitles();
        $vulnerabilities = array();
        foreach ($allSa as $satitle => $versions) {
            foreach ($versions as $version) {
                $found = strpos($version, $coreversion) !== false;
                if ($found) {
                   $vulnerabilities[] = $satitle;
                }
            }

        }
        return $vulnerabilities;
    }

    public function getVersionsAndTitles()
    {

        $url = 'https://www.drupal.org/api-d7/node.json?type=sa&status=1';
        $response = $this->client->get($url, [
            'http_errors' => false,
        ]);
        $obj = json_decode($response->getBody(), true);
        $list = $obj['list'];

        foreach ($list as $item) {
            $title = $item['title'] . PHP_EOL . PHP_EOL;
            $versionsAndTitles[$title] = array();

            foreach ($item['field_sa_version'] as $version) {
                $versionsAndTitles[$title][] = $version;

            }

        }
        return $versionsAndTitles;
    }



    public function versionScan($modules): array
    {
        $moduleVersions = array();
        $baseGitUrl = 'https://cgit.drupalcode.org/%module%/refs/';

        foreach ($modules as $module) {

            $url = filter_var(str_replace('%module%', $module, $baseGitUrl), FILTER_SANITIZE_URL);
            $response = $this->client->get($url, [
                'http_errors' => false,
            ]);

            $body = $response->getBody()->getContents();
            $dom = new \DOMDocument();
            @$dom->loadHTML($body);
            $rawLinks = $dom->getElementsByTagName('td');
            $moduleVersions[$module] = array();

            for ($i = 0; $i < $rawLinks->count(); $i++) {
                $link = $rawLinks->item($i);
                if ($link->getElementsByTagName('a')->item(0) != null) {
                    $unfilterdLink = $link->getElementsByTagName('a')->item(0)->getAttribute('href') . PHP_EOL;
                    $linkArray = explode('=', $unfilterdLink);

                    if (array_key_exists(1, $linkArray) && \strlen($linkArray[1]) < 41) {
                        if (!in_array($linkArray[1],
                                $moduleVersions[$module]) && !$this->hasOldModuleVersions($linkArray[1])) {
                            $moduleVersions[$module][] = $linkArray[1];
                        }
                    }

                }
            }
        }
        return $moduleVersions;
    }


    public function getJavascriptLinks($url)
    {
        $javascriptLinks = array();
        try {
            $response = $this->client->get($url, [
                'http_errors' => false,
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $body = $response->getBody()->getContents();
        $dom = new \DOMDocument();
        @$dom->loadHTML($body);
        $rawLinks = $dom->getElementsByTagName('a');

        foreach ($rawLinks as $link) {
            if ($link->nodeValue !== 'plain') {
                $javascriptLinks[$link->nodeValue] = $link->nodeValue;
            }
        }
        $javascriptLinks = array_slice($javascriptLinks, 12);

        return $javascriptLinks;
    }

    /**
     * @param $modulename
     * @param $jsFile
     * @return string
     */
    public function getScanBody($modulename, $jsFile)
    {

        $url = strtr('@base_uri/@module/js/@jsFile', [
            '@module' => $modulename,
            '@base_uri' => $this->baseModuleUrl,
            '@jsFile' => $jsFile,
        ]);


        $url = filter_var($url, FILTER_SANITIZE_URL);

        $scanResponse = $this->client->get($url, [
            'http_errors' => false,
        ]);

        return $scanResponse->getBody()->getContents();
    }


    public function getGitJavascriptBody($modulename, $inception, $jsFile)
    {
        $url = strtr('https://cgit.drupalcode.org/@module/plain/js/@jsFile?h=@version', [
            '@module' => $modulename,
            '@version' => $inception,
            '@jsFile' => $jsFile
        ]);

        $url = filter_var($url, FILTER_SANITIZE_URL);
        $response = $this->client->get($url, [
            'http_errors' => false,
        ]);
        return $response->getBody()->getContents();
    }


    public function diffJavascript($urls, $modulename, $inception): bool
    {
        // example end url: https://cgit.drupalcode.org/ctools/plain/js/ajax-responder.js?h=6.x-1.x
        foreach ($urls as $jsFile) {
            $gitBody = $this->getGitJavascriptBody($modulename, $inception, $jsFile);
            $scanBody = $this->getScanBody($modulename, $jsFile);

            if (md5($scanBody) == md5($gitBody)) {
                return true;
            }
        }
        return false;
    }






    //todo diffcss

    /**
     * @param \GuzzleHttp\
     * @param int $startNodeId
     * @param int $stopNodeId
     *
     * @return \Generator|int[]
     */
    public function findPublicNodeIds(int $startNodeId = 0, int $stopNodeId = 100)
    {
        for ($nid = $startNodeId; $nid <= $stopNodeId; $nid++) {
            $url = strtr('node/@nid', [
                '@nid' => $nid,
            ]);

            $response = $this->client->head($url, [
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 200) {
                yield $nid;
            }
        }
    }

    /**
     * @param Client $client
     */
    public function getVersionsOfModule(string $modulename)
    {

        $allVersions = $this->versionScan(array("module" => $modulename));
        $foundVersions = array();

        foreach ($allVersions[$modulename] as $VersionString) {
            $url = strtr('https://cgit.drupalcode.org/@module/tree/js?h=@version', [
                '@module' => $modulename,
                '@version' => $VersionString,
            ]);

            $url = filter_var($url, FILTER_SANITIZE_URL);
            $urls = $this->getJavascriptLinks($url);

            if ($this->diffJavascript($urls, $modulename, $VersionString)) {
                $foundVersions[] = $VersionString;

            }

        }

        return $foundVersions;
    }

    public function getBaseUrl(): string
    {

        $url = $this->client->getConfig('base_uri');
        $url = str_replace(array('/', 'http:', 'https:', ':'), '', $url);
        return $url;
    }


    /**
     * @param int $startNodeId
     * @param int $stopNodeId
     *
     * @return \Generator|Node[]
     */
    public function getPublicNodes(int $startNodeId = 0, int $stopNodeId = 100)
    {
        for ($nodeId = $startNodeId; $nodeId <= $stopNodeId; $nodeId++) {
            $url = strtr('node/@nid', [
                '@nid' => $nodeId,
            ]);

            $response = $this->client->get($url, [
                'http_errors' => false,
            ]);

            if ($node = $this->nodeFromResponse($nodeId, $response)) {
                yield $node;
            }
        }
    }


    /**
     * @param int $nodeId
     * @param ResponseInterface $response
     * @return Node|null
     */
    protected function nodeFromResponse(int $nodeId, ResponseInterface $response): ?Node
    {
        if ($response->getStatusCode() === 404) {
            return null;
        }

        $node = new Node();
        $node->setNodeId($nodeId);
        $node->setHttpStatusCode($response->getStatusCode());

        if ($body = $response->getBody()->getContents()) {
            $crawler = new Crawler();

            if ($classes = $crawler->filter('body')->attr('class')) {
                $classes = explode(' ', $classes);
                $classes = array_filter($classes, function ($class) {
                    return strpos($class, 'node--type-') === 0;
                });

                if ($classes) {
                    $contentType = reset($classes);
                    $contentType = str_replace('node--type-', '', $contentType);
                    $node->setContentType($contentType);
                }
            }
            $node->setTitle($crawler->filter('title')->first()->text());
        }

        return $node;
    }

    public function findBaseModuleUrl()
    {
        $baseUri = $this->client->getConfig('base_uri');
        $modules = file('modules.txt');
        $noHttpUri = str_replace(array('https://', 'http://'), '', $baseUri);
        $urls = [
            'modules/contrib/@module/@module.info.yml',
            'modules/@module/@module.info.yml',
            'sites/@noHttp/modules/contrib/@module/@module.info.yml',
        ];

        foreach ($modules as $module) {
            foreach ($urls as $templateUrl) {
                $url = strtr($templateUrl, [
                    '@module' => $module,
                    '@noHttp' => $noHttpUri,
                ]);

                $stripped = strtr($templateUrl, [
                    '@module' => '',
                    '@noHttp' => $noHttpUri,
                ]);

                $stripped = str_replace('.info.yml', '', $stripped);
                $url = filter_var($url, FILTER_SANITIZE_URL);

                $response = $this->client->head($url, [
                    'http_errors' => false,
                ]);

                if ($response->getStatusCode() === 403 || 200 === $response->getStatusCode()) {

                    return $stripped;
                }

            }


        }
        return 'localhost';

    }

    private function hasOldModuleVersions($str): bool
    {
        $found = false;
        for ($i = 1; $i < 8; $i++) {
            if (strpos($str, "$i.x-") !== false) {
                $found = true;
            }

        }
        return $found;

    }


}
