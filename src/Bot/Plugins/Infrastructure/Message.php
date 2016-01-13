<?php
namespace Bot\Plugins\Infrastructure;

use Bot\Core\CoreInterface;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Http;
use DI\Annotation\Inject;

abstract class Message extends Plugin
{
    const REACTION_TYPE = \Bot\Core\CoreInterface::MESSAGE;

    /**
     * @Inject("db")
     * @var Wrapper
     */
    protected $db;

    /**
     * @Inject("Core")
     * @var CoreInterface
     */
    protected $core;

    /**
     * @Inject
     * @var Http
     */
    protected $http;


    public function init()
    {
        $this->ev->on(self::REACTION_TYPE, [$this, 'processMessage']);
    }

    protected function checkFlood($sender, $chatName)
    {
        return true;
        $result = false;
        $flood = \Util::store('flood') ?: [];
        $chatHash = md5($chatName);
        if (!array_key_exists($chatHash, $flood)) {
            $flood[$chatHash] = [];
        }

        if (!in_array($sender, $flood[$chatHash])) {
            $flood[$chatHash][] = $sender;
            \Util::store('flood', $flood);
            $result = true;
        } else {
            \Util::console('Flood check triggered by ' . $sender . ' in ' . $chatName);
        }

        return $result;
    }

    abstract public function processMessage(
        $message,
        $sender,
        $senderName,
        $chatName,
        $receivedTime
    );
}
