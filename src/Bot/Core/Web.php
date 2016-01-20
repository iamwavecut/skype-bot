<?php
namespace Bot\Core;

use Bot\Filebase\Factory;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Http;
use DI\Annotation\Inject;
use DI\Container;
use GuzzleHttp;
use Psr\Http\Message\ResponseInterface;
use Sabre\Event\EventEmitter;

/**
 * Headless web skype, modern chats and private messaging only
 *
 * Class Web
 * @package Bot\Core
 */
class Web implements CoreInterface
{
    const STR_EVENT_MESSAGES = 'eventMessages';
    const STR_ID_CHAT = 19;
    const STR_ID_USER = 8;
    const TOKEN_TIMEOUT = 86400;

    /**
     * @Inject()
     * @var Container
     */
    protected $container;

    /**
     * @Inject("event")
     * @var EventEmitter
     */
    protected $ev;

    /**
     * Last polling timestamp
     *
     * @var int
     */
    protected $lastPoll = 0;

    /**
     * @var array|null
     */
    private $poll = null;

    /**
     * @Inject
     * @var Http
     */
    protected $http;

    /** @var Wrapper */
    private $tokenDb;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var string
     */
    private $regToken;

    /**
     * @var int
     */
    private $regTokenTimeout;

    /**
     * @var string
     */
    private $endpointId;

    /**
     * @var string
     */
    private $endpointUrl;

    private $resourceTypes = [
        'NewMessage',
        'ThreadUpdate',
    ];

    private $messageTypes = [
        'Text',
        'RichText',
        'Control/Typing',
        'Control/ClearTyping',
    ];

    public function initSkypeConnection()
    {
        $config = $this->container->get('config');
        $this->username = $config['web']['username'];
        $this->password = $config['web']['password'];
        $this->tokenDb = $this->quizDb = Factory::create(\Util::getLocalPath() . DS . 'coreWebToken.json');
        $this->lastPoll = time();


        \Util::debug('Start login');
        if ($this->tokenDb['skypeTokenTimeout'] && $this->tokenDb['skypeTokenTimeout'] < time()) {
            $this->tokenDb->clear();
            \Util::debug('Clearing timed out session');

        }

        if (!$this->tokenDb['skypeToken']) {
            \Util::debug('No token');

            $response = $this->webRequest(
                'https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com',
                'GET',
                null,
                [],
                false,
                true
            );
            \Util::debug('loginpage');

            $headersPos = strpos($response, "\r\n\r\n");
            $headers = $this->parseHeaders(substr($response, 0, $headersPos));
            $body = substr($response, $headersPos + 4);

            if (isset($headers['Set-Cookie'])) {
                if (is_string($headers['Set-Cookie'])) {
                    $headers['Set-Cookie'] = [$headers['Set-Cookie']];
                }
                foreach ($headers['Set-Cookie'] as $cookie) {
                    $match = [];
                    if ($this->tokenDb['skypeToken']) {
                        break;
                    }
                    if (!preg_match('/^refresh-token=(.+?);/', $cookie, $match)) {
                        continue;
                    }

                    if ($match && $match[1]) {
                        $this->setSkypeToken($match[1]);
                    }
                }
            }

            if (!$this->tokenDb['skypeToken']) {
                $pq = \phpQuery::newDocumentHTML($body);
                $pie = $pq->find('input[name=pie]')->val();
                $etm = $pq->find('input[name=etm]')->val();
                $jsTime = $pq->find('input[name=js_time]')->val();

                $response = $this->webRequest(
                    'https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com',
                    'POST',
                    [
                        'username' => $this->username,
                        'password' => $this->password,
                        'persistent' => 1,
                        'pie' => $pie,
                        'etm' => $etm,
                        'js_time' => $jsTime,
                        'timezone_field' => '+03|00',
                        'client_id' => 578134,
                        'redirect_uri' => 'https://web.skype.com/',
                    ]
                );
                $pq = \phpQuery::newDocumentHTML($response);
                $skypeToken = $pq->find('input[name=skypetoken]')->val();

                if ($skypeToken) {
                    $this->setSkypeToken($skypeToken);
                }
            }
        }

        $response = $this->webRequest(
            'https://web.skype.com/',
            'POST',
            ['skypetoken' => $this->tokenDb['skypeToken']]//,
        );

        $response = $this->webRequest(
            'https://client-s.gateway.messenger.live.com/v1/users/ME/endpoints',
            'POST',
            '{}',
            [
                'Accept' => 'application/json, text/javascript',
                'ClientInfo' => 'os=Windows; osVer=10; proc=Win32; lcid=en-us; deviceType=1; country=n/a; clientName=skype.com; clientVer=908/1.21.0.115//skype.com',
                'BehaviorOverride' => 'redirectAs404',
                'LockAndKey' => 'appId=msmsgs@msnmsgr.com; time=' . $this->lastPoll . '; lockAndKeyResponse=' . md5($this->lastPoll),
                'Content-Type' => 'application/json; charset=UTF-8',
                'Origin' => 'https://web.skype.com',
                'Referer' => 'https://web.skype.com/ru/',
                'ContextId' => 'tcid=' . $this->lastPoll . '00000000',
            ],
            false,
            true
        );
        $headersPos = strpos($response, "\r\n\r\n");
        $headers = $this->parseHeaders(substr($response, 0, $headersPos));
        $body = substr($response, $headersPos + 4);

        $endpointUrl = $headers['Location'];
        if ($endpointUrl) {
            $this->endpointUrl = rtrim(urldecode($endpointUrl), '/') . '/';
        }

        $regToken = $headers['Set-RegistrationToken'];
        if ($regToken) {
            list($regToken, $regTokenTimeout, $endpointId) = explode('; ', $regToken);

            $this->regToken = $regToken;
            $this->regTokenTimeout = str_replace('expires=', '', $regTokenTimeout);
            $this->endpointId = str_replace('endpointId=', '', $endpointId);

            $this->endpointUrl = str_replace(
                $this->endpointId . '/',
                '',
                urldecode($this->endpointUrl)
            );
            \Util::debug($this->endpointUrl);
            $response = $this->webRequest(
                $this->endpointUrl . $this->endpointId,
                'PUT',
                '{}',
                [
                    'Accept' => 'application/json, text/javascript',
                    'ClientInfo' => 'os=Windows; osVer=10; proc=Win32; lcid=en-us; deviceType=1; country=n/a; clientName=skype.com; clientVer=908/1.22.0.117//skype.com',
                    'BehaviorOverride' => 'redirectAs404',
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Origin' => 'https://web.skype.com',
                    'Referer' => 'https://web.skype.com/ru/',
                    'ContextId' => 'tcid=' . $this->lastPoll . '00000000',
                ],
                false,
                true,
                5
            );

            $response = $this->webRequest(
                $this->endpointUrl . 'SELF/subscriptions',
                'POST',
                '{"channelType":"httpLongPoll","template":"raw","interestedResources":["/v1/users/ME/conversations/ALL/properties","/v1/users/ME/conversations/ALL/messages","/v1/users/ME/contacts/ALL","/v1/threads/ALL"]}',
                [
                    'Accept' => 'application/json, text/javascript',
                ]
            );

            $this->initialized = true;
        }
    }

    /**
     * @param string $skypeToken
     */
    private function setSkypeToken($skypeToken)
    {
        $this->tokenDb['skypeToken'] = $skypeToken;
        $this->tokenDb['skypeTokenTimeout'] = $this->lastPoll + self::TOKEN_TIMEOUT;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $postData
     * @param array $headers
     * @param bool $async
     * @param bool $returnHeaders
     * @return ResponseInterface|GuzzleHttp\Promise\PromiseInterface
     * @throws \RuntimeException
     */
    private function webRequest(
        $url,
        $method = 'GET',
        $postData = null,
        array $headers = [],
        $async = false,
        $returnHeaders = false,
        $timeout = 3
    ) {
        $headers = array_merge(
            $headers,
            [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
                'Content-Length' => 0,
            ]
        );
        $cookiePath = \Util::getLocalPath() . DS . 'cookies' . DS . 'core';
        if (!is_dir($cookiePath)) {
            mkdir($cookiePath, 0777, true);
        }
        $host = parse_url($url, PHP_URL_HOST);
        $mh = curl_multi_init();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $async ? 300 : $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $async ? 300 : $timeout);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiePath . DS . 'cookie.' . $host . '.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath . DS . 'cookie.' . $host . '.txt');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, 1);
        curl_setopt($ch, CURLOPT_HEADER, $returnHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        switch ($method) {
            case 'PUT':
                curl_setopt($ch, CURLOPT_PUT, 1);
//                $headers['X-HTTP-Method-Override'] = 'PUT';
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                break;
        }

        if ($postData) {
            if (is_array($postData)) {
                $postData = http_build_query($postData);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $headers['Content-Length'] = strlen($postData);
        }

        if ($this->regToken) {
            $headers['RegistrationToken'] = $this->regToken;
        }

        if ($this->tokenDb['skypeToken']) {
            $headers['X-Skypetoken'] = $this->tokenDb['skypeToken'];
            $headers['Authentication'] = 'skypetoken=' . $this->tokenDb['skypeToken'];
            $headers['Authorization'] = 'skypetoken ' . $this->tokenDb['skypeToken'];
        }

        $linedHeaders = ['Expect:'];
        foreach ($headers as $headerName => $headerValue) {
            $linedHeaders[] = "{$headerName}: {$headerValue}";
        }


        curl_setopt($ch, CURLOPT_HTTPHEADER, $linedHeaders);

        curl_multi_add_handle($mh, $ch);

        if ($async) {
            return [$mh, $ch];
        }

        do {
            usleep(500);
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        $response = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);

        return $response;
    }

    public function poll($timeout = 1000)
    {
        $time = time();
        if ($this->initialized) {
            if (!$this->poll && $this->lastPoll <= $time - 1) {
                echo '.';
                $this->lastPoll = $time;
                $this->poll = $this->webRequest(
                    $this->endpointUrl . 'SELF/subscriptions/0/poll',
                    'POST',
                    null,
                    [
                        'Accept' => 'application/json, text/javascript',
                    ],
                    true,
                    false
                );
            } elseif ($this->poll) {
                list($mh, $ch) = $this->poll;

                $running = false;
                curl_multi_exec($mh, $running);
                if (!$running) {
                    $response = curl_multi_getcontent($ch);
                    curl_multi_remove_handle($mh, $ch);
                    curl_multi_close($mh);

//                    \Util::debug($response);

                    $events = json_decode($response, 1);
                    if ($events && array_key_exists(self::STR_EVENT_MESSAGES, $events)) {
                        $events = $events[self::STR_EVENT_MESSAGES];
                        foreach ($events as $event) {
                            $this->parseEventMessage($event);
                        }
                    }

                    $this->poll = null;
                }
            }
        }
    }

    public function parseEventMessage(array $eventMessage)
    {
        if (!in_array($eventMessage['resourceType'], $this->resourceTypes)) {
            \Util::debug($eventMessage);
            return;
        }

        $message = $eventMessage['resource'];

        switch ($eventMessage['resourceType']) {
            case 'NewMessage':
                $messageType = $message['messagetype'];
                break;
            case 'ThreadUpdate':
                $messageType = $eventMessage['resourceType'];
                break;
            default:
                \Util::debug($eventMessage);
                break;
        }

        if (!in_array($messageType, $this->messageTypes)) {
            return;
        }


        switch ($messageType) {
            case 'Text':
            case 'RichText':
                $body = $message['content'];
                $chatName = $this->extractId($message['conversationLink']);
                $sender = $this->extractId($message['from']);
                if ($sender == $this->container->get('config')['web']['username']) {
                    return; // ignore self messages
                }
                $senderName = $message['imdisplayname'];
                $time = new \DateTime($message['composetime']);
                $this->container->get('event')->emit(
                    \Bot\Core\CoreInterface::MESSAGE,
                    [$body, $sender, $senderName, $chatName, $time]
                );

                break;

            case 'ThreadActivity/DeleteMember':
                $usersLeft = [];
                $body = $message['content'];
                $body = pq($body);

                foreach ($body->find('target') as $target) {
                    $usersLeft[] = $this->extractId($target);
                }

                $chatName = $this->extractId($message['conversationLink']);
                $sender = $this->extractId($message['from']);
                $senderName = $message['imdisplayname'];
                $time = new \DateTime($message['composetime']);
                $this->container->get('event')->emit(
                    \Bot\Core\CoreInterface::KICKED,
                    [$sender, $senderName, $chatName, $usersLeft]
                );

                break;

            case 'ThreadActivity/AddMember':
                $usersAdded = [];
                $body = $message['content'];
                $body = pq($body);

                foreach ($body->find('target') as $target) {
                    $usersAdded[] = $this->extractId($target);
                }

                $chatName = $this->extractId($message['conversationLink']);
                $sender = $this->extractId($message['from']);
                $senderName = $message['imdisplayname'];
                $time = new \DateTime($message['composetime']);

                $this->container->get('event')->emit(
                    \Bot\Core\CoreInterface::ADDED,
                    [$sender, $senderName, $chatName, $usersAdded, $time]
                );

                break;
        }
    }

    public function send($targetId, $message)
    {
//        \Util::debug('send to ' . $targetId . ' ' . $message);
        $id = $this->generateMessageId();
        $response = $this->webRequest(
            $this->getEndpointHost() . 'v1/users/ME/conversations/' . $this->getIdType($targetId) . ':' . $targetId . '/messages',
            'POST',
            json_encode(["content" => $message, "messagetype" => "RichText", "contenttype" => "text", "clientmessageid" => $id]),
            [
                'Accept' => 'application/json, text/javascript',
            ]
        );

        $answer = json_decode($response, 1);
        if ($answer && !array_key_exists('OriginalArrivalTime', $answer)) {
            \Util::debug($answer);
        }
    }

    public function getProxy()
    {
        return null;
    }

    public function getProp($type, $prop)
    {
        return null;
    }

    /**
     * @param string $headersString
     * @return string[]
     */
    private function parseHeaders($headersString)
    {
        $resultHeaders = [];
        $headers = explode("\r\n", trim($headersString));
        array_shift($headers); // get rid of http header
        foreach ($headers as $headerLine) {
            list($name, $value) = explode(': ', $headerLine);
            if (array_key_exists($name, $resultHeaders)) {
                if (!is_array($resultHeaders[$name])) {
                    $resultHeaders[$name] = [$resultHeaders[$name]];
                }
                $resultHeaders[$name][] = $value;
            } else {
                $resultHeaders[$name] = $value;
            }
        }

        return $resultHeaders;
    }

    private function extractId($url)
    {
        return $id = substr($url, strrpos($url, ':') + 1);
    }

    private function getIdType($id)
    {
        return strstr($id, "@thread.skype") ? self::STR_ID_CHAT : self::STR_ID_USER;
    }

    private function getEndpointHost()
    {
        $endpoint = parse_url($this->endpointUrl);
        $baseUrl = $endpoint['scheme'] . '://' . $endpoint['host'];

        return $baseUrl . '/';
    }

    private function generateMessageId()
    {
        return strtr(microtime(), ['.' => '', ' ' => '']);
    }
}
