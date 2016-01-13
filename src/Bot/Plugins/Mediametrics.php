<?php
namespace Bot\Plugins;

use Bot\Core\CoreInterface;
use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

class Mediametrics extends Plugin
{
    /**
     * @Inject()
     * @var Http
     */
    protected $http;

    public function init()
    {
        // blank
    }

    public function getTopNews()
    {
        $topNews = [];
        $response = $this->http->getResponse('GET', $this->pluginConfig['url']);
        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
            $lines = preg_split('/\r?\n/', $body);
            $title = array_shift($lines); // csv title
            foreach ($lines as $line) {
                if ($line == '') {
                    continue;
                }
                list($url, $title, $visitors, $delta, $shift, $id) = explode("\t", $line);
                $topNews[] = [
                    'url'      => $url,
                    'title'    => $title,
                    'visitors' => $visitors,
                    'delta'    => $delta,
                    'shift'    => $shift,
                    'id'       => $id,
                ];
            }
        }

        return $topNews;
    }
}
