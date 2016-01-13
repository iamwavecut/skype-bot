<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

class Bash extends Plugin
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

    public function getBash()
    {
        $response = $this->http->getResponse('GET', 'http://bash.im/forweb/?u');
        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {

            $body = preg_replace("/'\\s*\\+\\s*'/", '', $body);
            $body = preg_replace('/<br(?:\s\/|)>/', "\r\n", $body);
            $body = preg_replace('/^.+1em 0\;\">/is', '', $body);
            $body = preg_replace('/<\/div><small>.+$/is', '', $body);

            return html_entity_decode(trim($body));
        }

        return null;
    }
}
