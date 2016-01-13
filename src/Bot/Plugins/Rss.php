<?php
namespace Bot\Plugins;

use Bot\Filebase\Factory;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Infrastructure\Timer;

class Rss extends Timer
{
    const PERIOD = 60 * 5;

    /**
     * @var Wrapper
     */
    protected $rssDb;

    public function init()
    {
        $this->ev->on('tick', [$this, 'processFeeds']);
        $this->rssDb = Factory::create(\Util::getLocalPath() . DS . 'rss.json');
    }

    public function processFeeds($time)
    {
        if ($time >= $this->currentTime + self::PERIOD) {
            $this->currentTime = $time;

            $timers = $this->rssDb['timers'] ?: [];
            $rssStr = [];

            foreach ($this->pluginConfig['feeds'] as $feedName => $feedData) {
                $timer = array_key_exists($feedName, $timers) ? $timers[$feedName] : 0;
                if (
                    !$feedData['enabled']
                    || (
                        $timer
                        && $timer + $this->pluginConfig['refresh_timeout'] > $time
                    )
                ) {
//                    \Util::debug('RSS not ready');
                    continue;
                }
//                \Util::debug('RSS get ' . $feedData['url']);

                $response = $this->http->getResponse(
                    'GET',
                    $feedData['url']
                );

                if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
//                    \Util::debug('RSS got');
                    $format = array_key_exists('format', $feedData)
                        ? $feedData['format']
                        : $this->pluginConfig['format'];

                    $items = \phpQuery::newDocumentXML($body)->find('channel item');
                    rsort($items);

                    foreach ($items as $item) {
                        \Util::debug($item);

                        $dateTime = \DateTime::createFromFormat(
                            \DateTime::RSS,
                            (string)pq($item)->find('pubDate')->text()
                        );

                        $matches = [];
                        \Util::debug([$dateTime->getTimestamp(), $timer]);

                        if (
                            $dateTime->getTimestamp() > $timer
                            && preg_match_all('/%(\w+)%/', $format, $matches)
                            && count($matches)
                        ) {

                            $timer = $dateTime->getTimestamp();
                            $matchesCount = count($matches[0]);
                            for ($i = 0; $i < $matchesCount; $i++) {
                                $rssStr[] = preg_replace(
                                    $matches[0][$i],
                                    (string)pq($item)->find($matches[1][$i])->text(),
                                    $format
                                );
                            }
                        }
                    }
                }

                if (count($rssStr)) {
                    foreach ($feedData['chats'] as $chatName) {
                        $this->core->send($chatName, implode(PHP_EOL, $rssStr));
                    }
                    $timers[$feedName] = $timer;
                }
            }

            $this->rssDb['timers'] = $timers;
        }
    }
}
