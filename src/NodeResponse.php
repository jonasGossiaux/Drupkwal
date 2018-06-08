<?php
/**
 * Created by PhpStorm.
 * User: bartv
 * Date: 18/02/2018
 * Time: 14:55
 */

namespace Duo\Scan;

use React\Promise\Promise;
use function Tickner\GuzzleToReactPromise\guzzleToReactPromise;

/**
 * Class NodeResponse
 *
 * @package Scanner
 */
class NodeResponse
{

    /**
     * @var int
     */
    protected $nodeId;

    /**
     * @var \GuzzleHttp\Promise\Promise
     */
    protected $promise;

    /**
     * NodeResponse constructor.
     *
     * @param int $nodeId
     * @param \GuzzleHttp\Promise\Promise $promise
     */
    public function __construct($nodeId, \GuzzleHttp\Promise\Promise $promise)
    {
        $this->nodeId = $nodeId;
        $this->promise = guzzleToReactPromise($promise);
    }

    /**
     * @return int
     */
    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    /**
     * @return \React\Promise\Promise
     */
    public function getPromise(): Promise
    {
        return $this->promise;
    }

}
