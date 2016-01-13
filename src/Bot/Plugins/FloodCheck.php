<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Timer;

class FloodCheck extends Timer
{
    const LOAD_ORDER = 100;

    public function init()
    {
        $this->ev->on('tick', [$this, 'checkAndClear']);
    }

    public function checkAndClear($time)
    {
        if ($time >= $this->currentTime + self::PERIOD) {
            $this->currentTime = $time;
            \Util::store('flood', []);
//            \Util::debug('Flood flush');
        }

        return true;
    }

}
