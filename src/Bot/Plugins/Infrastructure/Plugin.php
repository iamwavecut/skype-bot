<?php
namespace Bot\Plugins\Infrastructure;

use DI\Annotation\Inject;
use DI\Container;
use GuzzleHttp;
use Sabre\Event\EventEmitter;

abstract class Plugin implements PluginInterface
{
    /**
     * @Inject
     * @var Container
     */
    protected $container;

    /**
     * @Inject("config")
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $pluginConfig = [];

    /**
     * @Inject("event")
     * @var EventEmitter
     */
    protected $ev;

    abstract public function init();

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return count($this->pluginConfig)
               && array_key_exists('enabled', $this->pluginConfig)
               && $this->pluginConfig['enabled'];
    }

    /**
     * @param array $config
     */
    public function setPluginConfig($config)
    {
        $this->pluginConfig = $config;
    }
}
