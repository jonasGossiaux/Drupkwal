<?php
/**
 * Created by PhpStorm.
 * User: bartv
 * Date: 18/02/2018
 * Time: 14:36
 */

namespace Duo\Scan;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Disposable\CompositeDisposable;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler;
use Rx\SchedulerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;

/**
 * Class PublicNodeObservable
 *
 * @package Scanner
 */
class FoundModuleStream extends Observable
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    protected $basicClient;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var int
     */
    protected $concurrency;

    /**
     * @var \Rx\SchedulerInterface
     */
    protected $scheduler;

    /**
     * @var \GuzzleHttp\Pool
     */
    protected $pool;

    /**
     * @var string
     */
    protected $moduleFolder;

    /**
     * @var int
     */
    protected $amount;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Scanner
     */
    protected $scanner;

    /**
     * FoundModuleStream constructor.
     * @param LoopInterface $loop
     * @param string $baseUri
     * @param int $concurrency
     * @throws \Exception
     */

    public function __construct(
        LoopInterface $loop,
        string $baseUri,
        int $concurrency,
        int $amount,
        OutputInterface $output,
        Scanner $scanner,
        SchedulerInterface $scheduler = null,
        string $proxy = null
    ) {
        $this->scheduler = $scheduler ?? Scheduler::getDefault();
        $this->scanner = $scanner;
        $config = [
            'verify' => false,
            'base_uri' => $baseUri,
            'handler' => HandlerStack::create(new HttpClientAdapter($loop)),

        ];

        if (isset($proxy)) {
            $config['proxy'] = $proxy;
        }

        $this->client = new Client($config);
        $this->basicClient = $this->scanner->getClient();
        $this->concurrency = $concurrency;
        $this->moduleFolder = $this->scanner->baseModuleUrl;
        $this->amount = $amount;
        $this->output = $output;
    }

    /**
     * @param \Rx\ObserverInterface $observer
     *
     * @return \Rx\DisposableInterface
     */
    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        $callback = function () use ($observer) {

            $success = function (ResponseInterface $response, $index) use ($observer) {
                $modules = file('modules.txt');
                if ($response->getStatusCode() === 403) {
                    $observer->onNext($modules[$index]);
                }
            };

            $failure = function ($reason, $index) use ($observer) {

                $modules = file('modules.txt');
                if ($reason->getResponse()->getStatusCode() === 403) {
                    $observer->onNext($modules[$index]);
                }

            };

            $this->pool = new Pool($this->client, $this->getRequests(), [
                'fulfilled' => $success,
                'rejected' => $failure,
                'options' => ['http_errors' => false,]
            ]);

            $this->pool->promise()->then(function () use ($observer) {
                $observer->onCompleted();
            });
        };

        $compositeDisposable = new CompositeDisposable();
        $compositeDisposable->add($this->scheduler->schedule($callback));
        $cancelPromises = function () {
            /** @var $this ->pool pool */
            $this->pool->promise()->cancel();
        };
        $compositeDisposable->add(new CallbackDisposable($cancelPromises));
        return $compositeDisposable;

    }

    /**
     * @return \Generator|\GuzzleHttp\Psr7\Request[]
     */


    protected function getRequests()
    {
        $modules = file('modules.txt');
        $progressBar = new ProgressBar($this->output, $this->amount);
        $progressBar->start();
        for ($i = 0, $iMax = $this->amount; $i < $iMax; $i++) {
            $url = strtr('@baseModuleUrl/@module/@module.info.yml', [
                '@module' => $modules[$i],
                '@baseModuleUrl' => $this->moduleFolder,
            ]);

            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = str_replace('///', '/', $url);
            $progressBar->advance();
            yield new Request('HEAD', $url);

        }
        $progressBar->finish();
    }



}
