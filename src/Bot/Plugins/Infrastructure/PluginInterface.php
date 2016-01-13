<?php
namespace Bot\Plugins\Infrastructure;

interface PluginInterface
{
    public function init();

    public function isEnabled();

    public function setPluginConfig($config);
}
