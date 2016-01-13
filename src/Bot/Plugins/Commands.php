<?php

namespace Bot\Plugins;

use Bot\Filebase\Factory;
use Bot\Plugins\Infrastructure\Message;
use DI\Annotation\Inject;
use GuzzleHttp;
use Imgur\Api\Model\GalleryImage;

class Commands extends Message
{
    const CHATGROUP = 'commands';

    /**
     * @Inject
     * @var Translator
     */
    protected $translator;

    /**
     * @Inject
     * @var Anekdot
     */
    protected $anekdot;

    /**
     * @Inject
     * @var Bash
     */
    protected $bash;

    /**
     * @Inject
     * @var Imgur
     */
    protected $imgur;

    /**
     * @var GalleryImage[]
     */
    protected $imgurCache = [];

    /**
     * @Inject
     * @var Mediametrics
     */
    protected $mediametrics;

    public function init()
    {
        parent::init();

        Admin::registerGroup(self::CHATGROUP);
    }

    public function processMessage($message, $sender, $senderName, $chatName, $receivedTime)
    {
//        \Util::debug(self::CHATGROUP);

        $chats = $this->db['chats'] ?: [];

        if (!isset($chats[self::CHATGROUP]) || !in_array($chatName, $chats[self::CHATGROUP])) {
            return true;
        }

        if (substr($message, 0, 1) === '!' && strlen($message) > 1) {
            $arguments = explode(' ', mb_substr($message, 1));
            $command = array_shift($arguments);
            \Util::debug('Command: ' . $command . ' ' . implode(' ', $arguments) . ' in ' . $chatName);
            switch ($command) {
                case 'сколько':
                case 'ск':
                    if (!count($arguments)) {
                        return false;
                    }
                    $pair = trim(strtoupper(array_shift($arguments)));
                    if (!preg_match('/^\w{6}$/i', $pair)) {
                        return false;
                    }
                    $quantity = count($arguments) ? (float)array_shift($arguments) : 1;

                    $this->sendCrossRate($chatName, $pair, $quantity);

                    return false;

                    break;

                case 'баш':
                case 'bash':
                    if ($sender === 'ejakaaa') {
                        $this->core->send($chatName, 'Хуяш!');

                        return false;
                    }
                    $this->core->send($chatName, $this->bash->getBash());

                    return false;

                    break;

//            case 'афоризм':
//            case 'а':
//            case 'мудрость':
//                $afo = $this->getAfo();
//                $flag = $afo['country'] !== 'wn' ? ' (flag:' . $afo['country'] . ')' : '';
//                $msg = $afo['text'] . PHP_EOL . $afo['author'] . $flag;
//                $this->core->send($chatName, $msg);
//                break;

                case 'баян':
                case 'bayan':
                    if ($sender === 'ejakaaa') {
                        $this->core->send($chatName, 'Хуян!');

                        return false;

                    }
                    $anek = $this->anekdot->getAnekdot();
                    $this->core->send($chatName, $anek);

                    return false;

                    break;

                case 'tran':
                case 'пер':
                    $direction = strtolower(array_shift($arguments));
                    $subj = implode(' ', $arguments);
                    $this->core->send($chatName, $this->translator->getTranslation($subj, $direction));

                    return false;

                    break;
//
//            case 'google':
//            case 'гугл':
//                if ($sender == 'paranoidkilla') {
//                    $this->core->send($chatName, 'Вовка, ты заебал!');
//                    break;
//                };
//                if ($sender == 'daniel.lavrushin') {
//                    $this->core->send($chatName, 'Даник, ты заебал!');
//                    break;
//                }
//
//                $this->sendSearchResult($chatName, $arguments);
//
//                break;

                case 'курс':
                case 'kurs':
                    $newRates = $this->getRates(1);
                    $this->sendRates($newRates, [$chatName]);

                    return false;

                    break;

                case 'погода':
                case 'pogoda':
                    $city = strtolower(current($arguments));
                    $city = $city ?: 'минск';
                    $this->sendWeather($chatName, $city);

                    return false;

                    break;


                case 'пиво':
                case 'pivo':
                    $this->core->send($chatName, str_repeat('(beer)', mt_rand(1, 9)));

                    return false;

                    break;

//            case 'помощь':
//            case 'хелп':
//            case 'help':
//                $this->core->send($chatName,
//                    'Список команд: "' .
//                    '!курс|баш|баян|погода|пиво|гугл"'
//                );
//                break;

                case 'сокр':
                    $this->core->send($chatName, $this->shortenUrl($arguments[0]));

                    return false;

                    break;

                case 'имг':
                    if (!count($this->imgurCache)) {
                        $this->imgurCache = $this->imgur->api('gallery')->randomGalleryImages();
                    }

                    /** @var GalleryImage $image */
                    $image = array_pop($this->imgurCache);
                    $title = $image->getTitle();
                    $link = $image->getLink();
                    $link = $image->getAnimated() ? str_replace('.gif', '.gifv', $link) : $link;

                    $this->core->send($chatName, ($title ? $title . ' ' : '') . $link);

                    return false;

                    break;

                case 'нов':
                case 'новости':
                    $topNews = $this->mediametrics->getTopNews();
                    $msg = 'Новости в тренде: ' .PHP_EOL;

                    foreach ($topNews as $index => $newsLine) {
                        if ($index >= 3) {
                            break;
                        }
                        $msg .= "[{$newsLine['visitors']}";
//                        $msg .= $newsLine['delta'] ? ':' . (
//                            substr($newsLine['delta'], 0, 1) !== '-' ? '+' : ''
//                            ) . $newsLine['delta'] : '';
                        $msg .= '] ' . htmlspecialchars_decode($newsLine['title']);
//                        $msg .= ' ' . $this->shortenUrl('http://' . $newsLine['url']);
                        $msg .= PHP_EOL . ' http://' . $newsLine['url'];
                        $msg .= PHP_EOL;
                    }
                    $this->core->send($chatName, $msg);

                    return false;


                    break;

                default:
                    break;
            }
        }

        return true;
    }

    public function sendCrossRate($chatName, $pair, $quantity)
    {
        $rate = null;
        $url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.xchange'
            . '%20where%20pair%3D\'' . $pair . '\''
            . '&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';

        $response = $this->http->getResponse('GET', $url);
        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
            $dynamic = json_decode($body, true);
            if ($dynamic) {
                $xchange = $dynamic['query']['results']['rate'];
                $rate = sprintf("%.2f", $xchange['Ask'] * $quantity);
            }

            if ($rate) {
                $from = mb_substr($pair, 0, 3);
                $to = mb_substr($pair, 3, 3);

                $result = "($) {$quantity} {$from} = {$rate} {$to}";
                $this->core->send($chatName, $result);
            }
        }
    }

    public function sendRates($rates, array $chats = [])
    {
        if ($chats && count($chats)) {
            $ratesMsg = '';
            $currencies = [
                'USD' => null,
                'EUR' => null,
                'RUB' => null,
            ];
            $rateKeys = array_keys($rates);
            $rateLastDay = end($rateKeys);
            $ratePrevDay = prev($rateKeys);

            $rateLast = end($rates);
            $ratePrev = prev($rates);

            $diff = [];
            if ($rateLast && $rateLastDay) {
                if ($ratePrev && $ratePrevDay) {
                    foreach ($currencies as $currency => $value) {
                        $sign = '';
                        $currdif = $rateLast[$currency] - $ratePrev[$currency];
                        if ($currdif !== 0) {
                            $sign = (abs($currdif) === $currdif ? '+' : '-');
                        }
                        $diff[$currency] = $currdif !== 0 ? $sign . abs($currdif) : null;
                    }
                }

                $ratesMsg .= date('d.m.Y', $rateLastDay) . ': ' .
                    ' (flag:us) ' . $rateLast['USD'] . ' ' .
                    (isset($diff['USD']) ? "({$diff['USD']}) " : '') .
                    ' (flag:eu) ' . $rateLast['EUR'] . ' ' .
                    (isset($diff['EUR']) ? "({$diff['EUR']}) " : '') .
                    ' (flag:ru) ' . $rateLast['RUB'] . ' ' .
                    (isset($diff['RUB']) ? "({$diff['RUB']}) " : '') .
                    PHP_EOL;
            }
            foreach ($chats as $ratesChat) {
                $this->core->send($ratesChat, $ratesMsg);
            }
        }
    }

    public function sendSearchResult($chatName, $query)
    {
        if (is_array($query) && count($query)) {
            $query = implode(' ', $query);
            $response = $this->http->getResponse('GET', 'http://yandex.ru/yandsearch?text=' . urlencode($query));
            if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                $results = [];
                $resultsLimit = 5;
                $pq = \phpQuery::newDocumentHTML($query);
                $links = $pq->find('.serp-item__title-link');
                if ($links->count()) {
                    foreach ($links as $linkEl) {
                        $link = urldecode($pq->find($linkEl)->attr('href'));
                        $text = trim($pq->find($linkEl)->text());
                        if (mb_substr($link, 0, 2) === '//') {
                            $link = 'http:' . $link;
                        }
                        if ($link && mb_substr($link, 0, 11) !== 'http://yabs') {
                            $tmpMsg = $text . ' ' . $link;
                            if (!in_array($tmpMsg, $results)) {
                                $results[] = $tmpMsg;
                                $resultsLimit--;
                            }
                        }

                        if (!$resultsLimit) {
                            break;
                        }
                    }
                } else {
                    \Util::console($pq->text());
                }
                if (count($results)) {
                    $results = array_flip(array_flip($results));
                    $this->core->send($chatName, implode(PHP_EOL, $results));
                }
            } else {
                \Util::console($response->getBody());
            }
        }
    }

    public function getRates($force = false)
    {
        $ratesToGet = [
            'today'    => strtotime('today'),
            'tomorrow' => strtotime('tomorrow'),
        ];
        $ratesDb = Factory::create(\Util::getLocalPath() . DS . 'rates.json');
        $rates = $ratesDb['rates'] ?: [];

        foreach ($ratesToGet as $day => $stamp) {
            if ($force || empty($rates[$stamp])) {
                $response = $this->http->getResponse(
                    'GET',
                    'http://www.nbrb.by/Services/XmlExRates.aspx?ondate=' . date('m/d/Y', $stamp)
                );
                if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                    /** @var \SimpleXMLElement $rate */
                    $rate = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
                    if ((bool)$rate && isset($rate->Currency)) {
                        $newRate = [];
                        foreach ($rate->Currency as $currency) {
                            switch ((string)$currency->CharCode) {
                                case 'EUR':
                                case 'RUB':
                                case 'USD':
                                    $newRate[(string)$currency->CharCode] = round((float)$currency->Rate);
                                    break;
                            }
                        }
                        $rates[$stamp] = $newRate;
                    } else {
                        unset($rates[$stamp]);
                    }
                    ksort($rates);
                    $ratesDb['rates'] = $rates;
                }
            }
        }

        return $rates;
    }

    public function sendWeather($chatName, $city)
    {
        $cities = file_get_contents(DATA . DS . 'cities.json');
        $cities = json_decode($cities, true) ?: [];

        $cityId = isset($cities[$city]) ? $cities[$city] : null;

        $tomorrow = date('Y-m-d', strtotime('tomorrow'));

        //echo'weat;';
        if ($cityId) {
            $response = $this->http->getResponse(
                'GET',
                'http://export.yandex.ru/weather-ng/forecasts/' . $cityId . '.xml?' . time() . mt_rand() * 100
            );
            if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                $weather = simplexml_load_string($body);
                if (!$weather) {
                    return false;
                }
                /** @var \SimpleXMLElement $element */
                foreach ($weather as $element) {
                    if ($element['date'] === $tomorrow) {
                        foreach ($element as $part) {
                            if ($part['typeid'] == 2) {
                                $this->core->send(
                                    $chatName,
                                    'Погодка в ' .
                                    $weather['exactname'] .
                                    ' завтра: ' .
                                    $part->{'weather_type'} .
                                    ', от ' . $part->{'temperature-data'}->from .
                                    ' до ' .
                                    $part->{'temperature-data'}->to .
                                    ' (средняя ' .
                                    $part->{'temperature-data'}->avg .
                                    '), влажность ' .
                                    $part->humidity .
                                    '%, давление ' .
                                    $part->pressure .
                                    ', ветер ' . str_replace(
                                        [
                                            'sw',
                                            'se',
                                            'nw',
                                            'ne',
                                            's',
                                            'n',
                                            'e',
                                            'w',
                                        ],
                                        [
                                            'ю/з',
                                            'ю/в',
                                            'с/з',
                                            'с/в',
                                            'ю',
                                            'с',
                                            'в',
                                            'з',
                                        ],
                                        $part->{'wind_direction'}
                                    ) . ", " . $part->{'wind_speed'} . "м/сек."
                                );

                            }
                            //else {echo'notpart;';}
                        }
                    }
                    // else {echo'notel;';}
                }
            }
        }

        return false;
    }

    public function shortenUrl($url)
    {
        $result = false;
        if ($url) {
            $response = $this->http->getResponse(
                'POST',
                'http://shy.by/lp',
                [
                    GuzzleHttp\RequestOptions::BODY => http_build_query(['link' => $url]),
                ]
            );
            if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                $result = 'https://shy.by/l/' . $body;
//                var_dump($body->getContents());
            }
        }

        return $result;
    }
}
