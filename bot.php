<?php
set_time_limit(0);
mb_internal_encoding('UTF-8');
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', __DIR__);
define('SRC', ROOT . DS . 'src');
define('DATA', ROOT . DS . 'data');
define('BASE', ROOT . DS . 'db');
require ROOT . '/vendor/autoload.php';
/** @var \DI\Container $container */
use Interop\Container\ContainerInterface;

$builder = new DI\ContainerBuilder();
$builder->useAnnotations(true);
$builder->addDefinitions(
    [
        'config.path' => ROOT . DS . 'config',
        'config'      => function (ContainerInterface $c) {
            $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($c->get('config.path') . DS . 'bot.yml'));
            return $config;
        },
//        'loop'        => \DI\factory([React\EventLoop\Factory::class, 'create']),
        'loop'        => \DI\object(React\EventLoop\StreamSelectLoop::class), //fallback
        'event'       => \DI\object(Sabre\Event\EventEmitter::class),
    ]
);
$container = $builder->build();

spl_autoload_register(
    function ($className) use ($container) {
        $className = ltrim($className, "\\");
        $fileName = '';
        if (($lastNsPos = strrpos($className, "\\")) !== false) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace("\\", DS, $namespace) . DS;
        }
        $fileName .= str_replace('_', DS, $className) . '.php';
        $requirePath = __DIR__ . DS . 'src' . DS . $fileName;
        require $requirePath;
    }
);
$config = $container->get('config');
Util::$debug = $config['debug'];
$db = \Bot\Filebase\Factory::create(BASE . DS . 'main.json');
$container->set('Core', \DI\object("\\Bot\\Core\\Web"));
$container->set('db', $db);

Util::store('container', $container);

/**
 * @var \Bot\App $app
 */
$app = $container->get('\Bot\App');
$app->run();

