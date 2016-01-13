<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

class Shortik extends Plugin
{
    /**
     * @Inject
     * @var Http
     */
    protected $http;

    public function init()
    {
        // idle
    }

    public function get()
    {
        $response = $this->http->getResponse(
            'GET',
            'http://shortiki.com/export/api.php?format=json&type=random&amount=1'
        );
        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {

            $body = json_decode($body, true)[0];
            $body = preg_replace('#<br(?: /)?>#', PHP_EOL, $body['content']);

            return $body;
        }

        return null;
    }
}
