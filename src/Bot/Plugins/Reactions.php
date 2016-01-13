<?php
namespace Bot\Plugins;

use Bot\Core\Linux;
use Bot\Plugins\Infrastructure\Message;
use DI\Annotation\Inject;
use GuzzleHttp;
use Psr\Http\Message\ResponseInterface;

class Reactions extends Message
{
    const LOAD_ORDER = 1;
    const CHATGROUP = 'reactive';

    protected $lastHour = 0;

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
     * @var Shortik
     */
    protected $shortik;

    public function init()
    {
        parent::init();

        Admin::registerGroup(self::CHATGROUP);
    }

    public function processMessage(
        $message,
        $sender,
        $senderName,
        $chatName,
        $receivedTime
    ) {
//        \Util::debug(self::CHATGROUP);

        if (
            1
            && $this->checkFlood($sender, $chatName)
            && ($reactiveChats = $this->db['chats']['reactive'] ?: [])
            && in_array($chatName, $reactiveChats)
        ) {
            if ($message === '$' || $message === ';') {
                $this->sendBucks($chatName);

                return false;
            } elseif (preg_match('/((?:http(?:s?)\:\/\/)?(?:www\.)?youtu[^\s]+)/i', $message, $matches)) {
                $this->sendYoutubeDescription($chatName, $matches);

                return false;
            } elseif (
            preg_match(
                '/(http(?:s?):\/\/[\w\.\/\-\_0-9\%]+\.gif(?:[\?#]|$)|http:\/\/img\.leprosorium\.com\/\d+)/i',
                $message,
                $matches
            )
            ) {
                $this->sendGifSize($chatName, $matches);

                return false;
            } elseif (preg_match('#((?:http(?:s?)\://)?[^\s]+/[^\s]*)$#i', $message, $matches)) {
                $this->sendOpenGraphDescription($chatName, $matches);

                return false;
            } elseif (preg_match(
                '/^(tits|сиси|сись?ки|сисечки|груд[и]?|boob.+|tits|titties)$/iu',
                $message
            )
            ) {
                $num = str_pad(mt_rand(0, 3000), 5, '0', STR_PAD_LEFT);
                $link = 'http://media.oboobs.ru/boobs_preview/' . $num . '.jpg';
                $this->core->send($chatName, 'NSFW ' . $link);

                return false;
            } elseif (preg_match('/(^|\s)п[еия][з][д][аоеыу]/iu', $message)) {
                $cunt = (int)$this->db['cunt'] ?: 0;
                $cunt++;
                $this->db['cunt'] = $cunt;
                $this->core->send(
                    $chatName,
                    'Пизда помянута ' . \Util::writtenNum($cunt, 'раз,раза,раз', 'male')
                );

                return false;

            } elseif (preg_match('/^(попк.?|butt|поп.?|попочк.?|зад|жоп.?)$/iu', $message)
            ) {
                $num = str_pad(mt_rand(0, 2000), 5, '0', STR_PAD_LEFT);
                $link = 'http://media.obutts.ru/butts_preview/' . $num . '.jpg';
                $this->core->send($chatName, 'NSFW ' . $link);

                return false;

            } else {
                $langs = [
                    'be',
                    'uk',
                    'bg',
                    'la',
                ];
                $randomLang = $langs[mt_rand(0, count($langs) - 1)];
                $words = [];
                preg_match_all('/[А-ЯЁа-яё]+/u', $message, $words);

                //if (rand(1, 10000) > 9990 && count($words) && count($words[0]) > 1) {
                //    $this->core->send($chatName, implode(' ', $words[0]) . ', Карл');
                //} else
                if (mt_rand(1, 1000) > 995 && count($words) && count($words[0]) < 4) {
                    $huified = $this->huifyString(implode(' ', $words[0]));
                    if ($huified) {
                        $this->core->send($chatName, $huified . ', ' . $senderName);
                    }

                    return false;

                } elseif (mt_rand(1, 1000) > 995 && count($words) && count($words[0]) > 4) {
                    $this->core->send(
                        $chatName,
                        $this->translator->getTranslation(
                            implode(' ', $words[0]),
                            'ru-' . $randomLang
                        ) . ', ' . $senderName
                    );

                    return false;

                } elseif (strlen($message) > 1 && preg_match('/\?\)*\(*$/', $message) && mt_rand(1, 100) > 95) {

                    // TODO move to file
                    $answers = [
                        "Мне кажется — да",
                        "Бесспорно",
                        "Решено - да",
                        "Вероятнее всего",
                        "Хорошие перспективы",
                        "Знаки говорят — да",
                        "Никаких сомнений",
                        "Да",
                        "Определённо да",
                        "Можешь положиться",
                        "Пока не ясно, попробуй снова",
                        "Спроси позже",
                        "Лучше не рассказывать",
                        "Сейчас нельзя предсказать",
                        "Сконцентрируйся и спроси опять",
                        "Даже не думай",
                        "Мой ответ — нет",
                        "По моим данным — нет",
                        "Перспективы не очень хорошие",
                        "Весьма сомнительно",
                        "Подожди час",
                        "Демура утверждал обратное",
                        "Это все Тёркин",
                        "Нет времени объяснять",
                        "Все не так однозначно",
                        "Теперь Вы дипломированный экономист",
                        "А в Африке дети голодают",
                        "Что ты как маленький",
                        "Ой все",
                        "Не знаю",
                        "Йован жив",
                        "Быть или не быть? Вот в чем вопрос",
                    ];
                    $answer = $answers[mt_rand(0, count($answers) - 1)];

                    if ($answer) {
                        $this->core->send($chatName, $answer . ', ' . $senderName);
                    }

                    return false;

                } elseif (
                    mt_rand(1, 1000) > 995
                    || (preg_match('/' . mb_strtolower(Linux::$botName) . '/iu', $message) && mt_rand(1, 100) > 80)
                ) {
                    if ($sender === 'ejakaaa') {
                        return true;
                    }

                    // no more random jokes, let it be greetings
                    $msg = \Util::getRandomGreeting($senderName);

                    if ($msg) {
                        $this->core->send($chatName, $msg);
                    }

                    return false;
                }
            }
        }

        return true;
    }

    private function parseYoutubeUrl(
        $url
    ) {
        $result = false;
        $url = trim($url);

        if (strpos($url, 'http') !== false) {
            $url = preg_replace(
                "#((http|https)://(\\S*?\\.\\S*?))(\\s|\\;|\\)|\\]|\\[|\\{|\\}|,|\"|'|:|\\<|$|\\.\\s)#i",
                "$1",
                $url
            );
        }

        $parsed_url = parse_url($url);
        if ($parsed_url && isset($parsed_url['host'])) {
            switch ($parsed_url['host']) {
                case 'youtu.be':
                    $result = mb_substr($parsed_url['path'], 1);
                    break;

                case 'youtube.com':
                case 'www.youtube.com':
                default:
                    if ($parsed_url['path'] === '/watch') {
                        $start = strpos($parsed_url['query'], 'v=') + 2;
                        $end = strpos($parsed_url['query'], '&', $start);
                        $length = ($end !== false && ($end - $start > 0)) ? $end - $start : strlen(
                                $parsed_url['query']
                            ) - $start;
                        $result = mb_substr($parsed_url['query'], $start, $length);
                    } else {
                        if (strpos($parsed_url['path'], '/embed') === 0) {
                            $last = end(explode('/', $parsed_url['path']));
                            $end = strpos($last, '?', 0);
                            $till = $end !== false ? $end : strlen($last);
                            $result = mb_substr($last, 0, $till);
                        } else {
                            if (strpos($parsed_url['path'], '/v/') === 0) {
                                $last = end(explode('/', $parsed_url['path']));
                                $end = strpos($last, '?', 0);
                                $till = $end !== false ? $end : strlen($last);
                                $result = mb_substr($last, 0, $till);
                            } else {
                                $result = $url;
                            }
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    private function huifyString(
        $str
    ) {
        $matches = [];
        preg_match_all('/[А-ЯЁа-яё]+/', $str, $matches);
        /** @var array $words */
        $words = end($matches);

        foreach ($words as $key => $word) {
            $matches = [];
            if (
                preg_match(
                    '/^[^ауоыиэяюёе]*(?<glasnaya>[ауоыиэяюёе])(?<konec>.+)/iu',
                    $word,
                    $matches
                )
                && $matches
                && count($matches)
                && $matches['glasnaya']
                && $matches['konec']
            ) {
                $glas = preg_replace(
                    [
                        '/^а/iu',
                        '/^о/iu',
                        '/^э/iu',
                        '/^у/iu',
                        '/^ы/iu',
                    ],
                    [
                        'я',
                        'ё',
                        'е',
                        'ю',
                        'и',
                    ],
                    $matches['glasnaya']
                );

                $word .= '-' . 'ху' . $glas . $matches['konec'];

                $words[$key] = $word;
            }
        }

        return implode($words);
    }

    private function sendBucks($chatName)
    {
        $futures = [
            1  => 'F',
            2  => 'G',
            3  => 'H',
            4  => 'J',
            5  => 'K',
            6  => 'M',
            7  => 'N',
            8  => 'Q',
            9  => 'U',
            10 => 'V',
            11 => 'X',
            12 => 'Z',
        ];
        $futureDate = new \DateTime();
        $futureDate->add(new \DateInterval('P1M15D'));
        $future = "BZ" . $futures[$futureDate->format('n')] . $futureDate->format('y') . '.NYM';
        $url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yql.query.multi'
            . '%20where%20queries%3D%22' . 'select+*+from+yahoo.finance.quoteslist+where+'
            . 'symbol=\'' . $future . '\'%'
            . '3Bselect+*+from+yahoo.finance.xchange'
            . '+where+pair+=+\'USDRUB,EURRUB,USDBYR,USDUAH,USDKZT,EURUSD,CNYRUB\'%22'
            . '&format=json&env=store%3A%2F%2Fdatatables.'
            . 'org%2Falltableswithkeys&callback=';

        $response = $this->http->getResponse(
            'GET',
            $url,
            [
                GuzzleHttp\RequestOptions::HEADERS => [
                    "User-Agent"       => "Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
                    "Accept"           => "application/json, text/plain, */*; q=0.01",
                    "Accept-Language"  => "en-us,en;q=0.5",
                    "Accept-Encoding"  => "gzip, deflate",
                    "Accept-Charset"   => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
                    "Connection"       => "close",
                    "X-Requested-With" => "XMLHttpRequest",
                    "Referer"          => "https://query.yahooapis.com/v1/public/yql",
                    "Cache-Control"    => "no-cache",
                    "Pragma"           => "no-cache",
                    "Origin"           => "https://query.yahooapis.com/",
                ],
            ]
        );

        if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
            $rates = [];

            $dynamic = json_decode($body, 1);

            $oil = $dynamic['query']['results']['results'][0]['quote']['LastTradePriceOnly'];
            $xchange = $dynamic['query']['results']['results'][1]['rate'];
            $rates['OIL'] = sprintf("%.2f", $oil);
            $rates['USDRUB'] = sprintf("%.2f", $xchange[0]['Ask']);
            $rates['EURRUB'] = sprintf("%.2f", $xchange[1]['Ask']);
            $rates['BYR'] = sprintf("%.2f", $xchange[2]['Ask']);
            $rates['UAH'] = sprintf("%.2f", $xchange[3]['Ask']);
            $rates['KZT'] = sprintf("%.2f", $xchange[4]['Ask']);
            $rates['EUR'] = sprintf("%.3f", $xchange[5]['Ask']);
            $rates['CNYRUB'] = sprintf("%.2f", $xchange[6]['Ask']);
            $rates['OILRUB'] = round($rates['OIL'] * $rates['USDRUB']);


            $ratediff = function ($rate, $oldRate) {
                $msg = '';
                if ($rate !== $oldRate) {
                    $diffRate = $rate - $oldRate;

                    $sign = abs($diffRate) === $diffRate ? '+' : '-';
                    $msg .= ' (';
                    $msg .= $sign . ltrim((string)round(abs($diffRate), 2), '0');
                    $msg .= ')';
                }

                return $msg;
            };
            $ratesCache = empty(\Util::store('ratesCache')) ? $rates : \Util::store('ratesCache');

            $usddiff = $ratediff($rates['USDRUB'], $ratesCache['USDRUB']);
            $eurdiff = $ratediff($rates['EURRUB'], $ratesCache['EURRUB']);
            $oildiff = $ratediff($rates['OIL'], $ratesCache['OIL']);
            $uahdiff = $ratediff($rates['UAH'], $ratesCache['UAH']);
            $oilrubdiff = $ratediff(
                round($rates['OIL'] * $rates['USDRUB']),
                $ratesCache['OILRUB']
            );
            $result =
                "(mp) (flag:us) " . "{$rates['USDRUB']}{$usddiff}   "
                . "(flag:eu) " . "{$rates['EURRUB']}{$eurdiff}   "
                . "(flag:sa) " . "\${$rates['OIL']}{$oildiff} / Р"
                . round($rates['OIL'] * $rates['USDRUB']) . $oilrubdiff . "   "
                . "((flag:eu) / (flag:us)) {$rates['EUR']}   "
                . "(flag:ua) {$rates['UAH']}{$uahdiff}";

            \Util::store('ratesCache', $rates);
            $this->core->send($chatName, $result);
        } else {
            \Util::debug(
                'Yahoo rates response code: ' . ($response instanceof ResponseInterface ? $response->getStatusCode() : 'No response')
            );
        }
    }

    /**
     * @param string $chatName
     * @param array $matches
     */
    private function sendYoutubeDescription($chatName, $matches)
    {

        $ytCache = \Util::store('YT_CACHE') ?: [];
        array_shift($matches);
        foreach ($matches as $match) {
            $id = $this->parseYoutubeUrl($match);
            if ($id) {
                $description = '';
                if (array_key_exists($id, $ytCache)) {
                    $description = $ytCache[$id];
                } else {
                    $url =
                        $this->pluginConfig['google']['youtube']['api']['url'] . "?id={$id}" .
                        "&part=snippet,contentDetails&fields=items(snippet(title),contentDetails(duration))" .
                        "&key=" . $this->pluginConfig['google']['youtube']['api']['key'];
                    $response = $this->http->getResponse('get', $url);

                    if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                        $body = json_decode($body, true);
                        if ($body && isset($body['items']) & count($body['items'])) {
                            $item = $body['items'][0];
                            $dur = new \DateInterval($item['contentDetails']['duration']);
                            $title = $item['snippet']['title'];
                            $description = "[^ YT " . $dur->format('%i:%S') . "] " . $title;
                            $ytCache[$id] = $description;
                            \Util::store('YT_CACHE', $ytCache);
                        }


                    }
                }
                if ($description) {
                    $this->core->send(
                        $chatName,
                        $description
                    );
                }
            }
        }
    }

    private function sendGifSize(
        $chatName,
        $matches
    ) {
        $gifCache = \Util::store('GIF_CACHE') ?: [];
        $gfyLink = '';

        array_shift($matches);
        $url = current($matches);
        $url = str_replace('https://std3.', 'http://std3.', $url);
        $urlHash = md5($url);
        if (!array_key_exists($urlHash, $gifCache)) {
            $gifSize = 0;
            $contentType = '';

            $gfycatResponse = $this->http->getResponse('GET', $url);
            if ($gfycatResponse && $gfycatResponse->getStatusCode() === 200 && $body = $gfycatResponse->getBody()) {
//                \Util::debug('GFYCAT MATCH');
                $gfy = json_decode($body, true);

                if ($gfy && $gfy['urlKnown']) {
                    $gfyLink = ' [GFY ' . (string)$gfy['gfyUrl'];

                    $gfy = json_decode(
                        file_get_contents('http://gfycat.com/cajax/get/' . $gfy['gfyName']),
                        true
                    )['gfyItem'];

                    $gfyLink .= ' ' . \Util::humanFileSize($gfy['mp4Size']);
                    $gifSize = $gfy['gifSize'];
                    $gfyLink .= ']';
                    $contentType = 'GIF';
                }

            }

            if (!$gfyLink) {
//                \Util::debug('NO GFYCAT MATCH');
                $response = $this->http->getResponse('HEAD', $url);
                if ($response && $response->getStatusCode() === 200) {
//                \Util::debug($response->getHeader('Content-Type'));
                    if (preg_match('/image\/(\w+)/i', current($response->getHeader('Content-Type')), $matches2)) {
                        $contentType = $matches2[1];
                    }
                    $gifSize = (int)current($response->getHeader('Content-Length'));
                }
            }
        } else {
            list($contentType, $gifSize) = $gifCache[$urlHash];
        }

        $message = "";
        if ($contentType) {
            $message = "[^ " . strtoupper($contentType);
            if ($gifSize) {
                $message .= ' ' . \Util::humanFileSize($gifSize);
            }
            $message .= ']';
            if ($gfyLink) {
                $message .= $gfyLink;
            }
        }
        if ($message) {
            $gifCache[$urlHash] = [$contentType, $gifSize];
            \Util::store('GIF_CACHE', $gifCache);
            $this->core->send($chatName, $message);
        }
    }

    /**
     * @param string $chatName
     * @param array $matches
     * @return string
     */
    private function sendOpenGraphDescription(
        $chatName,
        $matches
    ) {
        $ogCache = \Util::store('OG_CACHE') ?: [];
        array_shift($matches);
        foreach ($matches as $url) {
            $urlHash = md5($url);
            $metaData = [];

            if (!array_key_exists($urlHash, $ogCache)) {


                $response = $this->http->getResponse('GET', $url);
                if ($response && $body = $response->getBody()) {
                    try {
                        $pq = \phpQuery::newDocument($body);
                    } catch (\Exception $e) {
                        return false;
                    }
                    $metaTags = $pq->find('html head meta[property^=og]');
                    if ($metaTags->count()) {
                        $title = $pq->find('html head meta[property=og:title]')->attr('content');
                        $metaData['title'] = $title;
                        $description = $pq->find('html head meta[property=og:decription]')->attr('content');
                        $metaData['description'] = $description;
                        $metaData['duration'] = $pq->find('html head meta[property=og:video:duration]')->attr(
                            'content'
                        );
                    }
                }
            } else {
                $metaData = $ogCache[$urlHash];
            }
            if (count($metaData)) {
                $ogCache[$urlHash] = $metaData;
                \Util::store('OG_CACHE', $ogCache);

                $msg = '[^';
                if ($metaData['duration']) {
                    $msg .= ' ' . date('i:s', (int)$metaData['duration']);
                }
                $msg .= ']';
                if ($metaData['title'] || $metaData['description']) {
                    $msg .= ' ' . ($metaData['title'] ?: $metaData['description']);
                }

                $this->core->send($chatName, $msg);
            }
        }

        return false;
    }
}
