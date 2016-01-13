<?php
namespace Bot\Plugins\Infrastructure;

use Bot\Core\CoreInterface;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Http;
use DI\Annotation\Inject;

abstract class Timer extends Plugin
{
    const PERIOD = 1.0;

    protected $currentTime = 0;

    /**
     * @Inject("db")
     * @var Wrapper
     */
    protected $db;

    /**
     * @Inject("Core")
     * @var CoreInterface
     */
    protected $core;

    /**
     * @Inject
     * @var Http
     */
    protected $http;


    public function getPeriod()
    {
        return static::PERIOD;
    }
}
