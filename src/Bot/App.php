<?php
namespace Bot;

use Bot\Core\CoreInterface;
use Bot\Filebase\Wrapper;
use DI\Annotation\Inject;
use DI\Container;
use React\EventLoop\LoopInterface;
use Sabre\Event\EventEmitter;

class App
{
    /**
     * @param Wrapper $db
     */
    private $db;

    /**
     * @Inject
     * @var Container
     */
    private $container;

    /**
     * @Inject("config")
     * @var \ArrayAccess
     */
    private $config;

    /**
     * @Inject("Core")
     * @var CoreInterface
     */
    private $core;

    public function run()
    {
        $this->db = $this->container->get('db');
        $this->db['last_run'] = date('Y-m-d H:i:s');

        \Util::debug('Connecting to Skype');
        $this->core->initSkypeConnection();

        \Util::debug('Initializing plugins');
        $this->container->get('Bot\Plugins\Infrastructure\Broker')->initPlugins();

        /** @var LoopInterface $loop */
        $loop = $this->container->get('loop');

        /** @var EventEmitter $ev */
        $ev = $this->container->get('event');

        if (isset($this->db['restart']) && $this->db['restart']) {
            list($chatName, $restartTime) = $this->db['restart'];
            $this->core->send($chatName, 'Я сделяль!');
            unset($this->db['restart']);
        }

        $loop->addPeriodicTimer(
            0.0001,
            function () use ($ev, $loop) {
                $this->core->poll();
//                $this->webCore->poll();
                $ev->emit('tick', [microtime(true)]);
            }
        );

        \Util::debug('Running main loop');
        $loop->run();
    }
}
