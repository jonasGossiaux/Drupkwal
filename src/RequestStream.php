<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 30/03/2018
 * Time: 14:38
 */

namespace Duo\Scan;


use Rx\DisposableInterface;
use Rx\Observable;
use Rx\ObserverInterface;

class RequestStream extends Observable
{

    /**
     * @param ObserverInterface $observer
     * @return DisposableInterface
     */
    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        // TODO: Implement _subscribe() method.

    }
}