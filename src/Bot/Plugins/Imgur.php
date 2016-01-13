<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Plugin;

class Imgur extends Plugin
{
    /**
     * @var \Imgur\Client
     */
    private $client;

    public function init()
    {
        $client = new \Imgur\Client();
        $client->setOption('client_id', $this->pluginConfig['api']['id']);
        $client->setOption('client_secret', $this->pluginConfig['api']['secret']);

        if ($this->pluginConfig['session']['token']) {
            $client->setAccessToken($this->pluginConfig['api']['token']);
            if($client->checkAccessTokenExpired()) {
                $client->refreshToken();
            } else {
                \Util::console('Obtain new Imgur token and add it to config: '.$client->getAuthenticationUrl());
            }
        }

        $this->client = $client;
    }

    public function  __call($method, $args) {
        return call_user_func_array([$this->client, $method], $args);
    }
}
