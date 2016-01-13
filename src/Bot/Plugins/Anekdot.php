<?php

namespace Bot\Plugins;


use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

class Anekdot extends Plugin
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

    public function getAnekdot()
    {
        $response = $this->http->getResponse('GET', 'http://www.anekdot.ru/rss/random.html');
        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {

            $body = preg_replace("/^.+var anekdot_texts = \\['/is", '', $body);
            $body = preg_replace('/<br(?:\s\/|)>/', "\r\n", $body);
            $body = preg_replace("/'\\];.+$/s", '', $body);
            $body = explode("','", $body);
            $body = $body[array_rand($body)];

            return \Util::toUTF(html_entity_decode(trim($body)));
        }

        return null;
    }
}

