<?php
namespace Bot\Plugins\Infrastructure;

use Bot\Traits\CamelCase;
use DI\Annotation\Inject;
use DI\Container;
use DI\DependencyException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Broker
{
    use CamelCase;

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

    public function initPlugins()
    {
        $namespace = implode("\\",array_slice(explode("\\", __NAMESPACE__),0,-1));


        foreach($this->config['plugins'] as $pluginName) {
            $pluginClassName =$this->toCamelCase($pluginName);
            $pluginConfig = $this->loadPluginConfig($pluginName);
            if ($pluginConfig && count($pluginConfig)) {

                /**
                 * Enabled check is quite broken because of that plugins use DI to load and it meets deps. A subject
                 * to refactoring.
                 */
                if ($pluginConfig['enabled']) {
                    $loadedPlugin = $this->container->get($namespace . "\\" . $pluginClassName);
                    $loadedPlugin->setPluginConfig($pluginConfig);
                    $loadedPlugin->init();
                    \Util::debug(" + Plugin \"{$pluginName}\" enabled");

                } else {
                    \Util::debug(" - Plugin \"{$pluginName}\" disabled");
                }
                }else {
                \Util::debug('Config not found: ' . $pluginName);
            }
        }
    }

    /**
     * @param string $configName
     * @return array|null
     * @throws \DI\NotFoundException
     * @throws ParseException
     * @throws \InvalidArgumentException
     * @throws DependencyException
     */
    private function loadPluginConfig($configName)
    {
        $configPath = $this->container->get('config.path') . DS . 'plugins' . DS . $configName . '.yml';
        if (!file_exists($configPath)) {
            return null;
        }

        return Yaml::parse($configPath);
    }

}
