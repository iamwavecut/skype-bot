<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Plugin;
use GuzzleHttp;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Http extends Plugin
{
    public function init()
    {
        //idle
    }

    /**
     * @param $method
     * @param $url
     * @param array $options
     * @return ResponseInterface
     * @throws \RuntimeException
     */
    public function getResponse($method, $url, array $options = [])
    {
        $host = parse_url($url, PHP_URL_HOST);
        $response = false;
        $client = $this->getHttpClient();
        $cookiePath = \Util::getLocalPath() . DS . 'cookies';
        if (!is_dir($cookiePath)) {
            mkdir($cookiePath);
        }
        $options = array_merge(
            [
                GuzzleHttp\RequestOptions::COOKIES
                => new GuzzleHttp\Cookie\FileCookieJar($cookiePath . DS . 'cookie.' . $host . '.json'),
                GuzzleHttp\RequestOptions::VERIFY
                => false,
                GuzzleHttp\RequestOptions::ALLOW_REDIRECTS
                => true,
                GuzzleHttp\RequestOptions::HEADERS
                => ['User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1'],
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT
                => $this->pluginConfig ? $this->pluginConfig['connect_timeout'] : 10,
                GuzzleHttp\RequestOptions::TIMEOUT
                => $this->pluginConfig ? $this->pluginConfig['response_timeout'] : 10,
            ],
            $options
        );
        try {
            $response = $client->request($method, $url, $options);
        } catch (GuzzleHttp\Exception\RequestException $ce) {
            $r = $ce->getResponse();
            if ($r instanceof RequestInterface) {
                \Util::console($ce->getMessage() . ' ' . $r->getBody());
            } else {
                \Util::console($ce->getMessage());
            }
            $response = $r;
        } catch (\Exception $e) {
            \Util::console($e->getMessage());
            $response = null;
        } finally {
            return $response;
        }
    }

    /**
     * @return GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        /** @var GuzzleHttp\Client $client */
        $client = $this->container->get('GuzzleHttp\Client');

        return $client;
    }
}
