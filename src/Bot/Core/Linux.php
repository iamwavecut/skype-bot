<?php
namespace Bot\Core;

use DI\Annotation\Inject;
use DI\Container;
use Sabre\Event\EventEmitter;

/**
 * Class Linux
 * @package Bot\Core
 * @deprecated
 */
class Linux implements CoreInterface
{
    const DEBUG = true;

    /** @var \DBus */
    protected $dbus;
    protected $proxy;
    public static $botHandle = 'outerspacebot';
    public static $botName = 'робот';

    /**
     * @Inject
     * @var Container
     */
    protected $container;

    /**
     * @Inject("event")
     * @var EventEmitter
     */
    protected $ev;

    public function initSkypeConnection()
    {
        if (!extension_loaded('dbus')) {
            throw new \Exception('No DBus extension loaded. Exiting.');
        }
        $this->dbus = new \Dbus(\Dbus::BUS_SESSION, true);
        $this->proxy = $this->dbus->createProxy('com.Skype.API', '/com/Skype', 'com.Skype.API');
        $this->proxy->Invoke('NAME PHP');
        $this->proxy->Invoke('PROTOCOL 8');
        $this->dbus->registerObject('/com/Skype/Client', 'com.Skype.API.Client', 'Bot\Core\Linux');
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function poll($timeout = 100)
    {
        $this->dbus->waitLoop($timeout);
    }

    public function send($targetId, $message)
    {
        $this->proxy->Invoke('CHATMESSAGE ' . $targetId . ' ' . iconv("UTF-8", "UTF-8//IGNORE", $message));
    }

    public static function notify($message)
    {
//        if (self::DEBUG) {
//            \Util::debug('> ' . $message);
//        }

        $messageArray = explode(' ', $message);
        switch ($messageArray[0]) {
            case 'CURRENTUSERHANDLE':
                self::$botHandle = $messageArray[1];
                self::$botName = trim(\Util::store('container')->get('Core')->getProp('PROFILE', 'FULLNAME'));
//                \Util::debug(self::$botName);
                break;

            case 'CHATMESSAGE':
                if ($messageArray[3] === 'RECEIVED'/* || $messageArray[3] === 'SENT'*/) {
                    \Util::store('container')->get('Core')->parseReceivedMessage($messageArray[1]);
                }
                break;
        }
    }

    public function parseReceivedMessage($message_id)
    {
        $messageType = $this->getMessageProp($message_id, 'TYPE');
        switch ($messageType) {
            case 'SAID':
                $chatName = $this->getMessageProp($message_id, 'CHATNAME');
                $message = $this->getMessageProp($message_id, 'BODY');
                $sender = $this->getMessageProp($message_id, 'FROM_HANDLE');
                $senderName = $this->getMessageProp($message_id, 'FROM_DISPNAME');
                \Util::store('container')->get('event')->emit(
                    \Bot\Core\CoreInterface::MESSAGE,
                    [$message, $sender, $senderName, $chatName, self::$botHandle]
                );

                break;

            case 'KICKED':
                $chatName = $this->getMessageProp($message_id, 'CHATNAME');
                $sender = $this->getMessageProp($message_id, 'FROM_HANDLE');
                $senderName = $this->getMessageProp($message_id, 'FROM_DISPNAME');
                $usersKicked = explode(' ', $this->getMessageProp($message_id, 'USERS'));
                \Util::store('container')->get('event')->emit(
                    \Bot\Core\CoreInterface::KICKED,
                    [$sender, $senderName, $chatName, $usersKicked]
                );

                break;

            case 'ADDEDMEMBERS':
                $chatName = $this->getMessageProp($message_id, 'CHATNAME');
                $sender = $this->getMessageProp($message_id, 'FROM_HANDLE');
                $senderName = $this->getMessageProp($message_id, 'FROM_DISPNAME');
                $usersAdded = explode(' ', $this->getMessageProp($message_id, 'USERS'));
                \Util::store('container')->get('event')->emit(
                    \Bot\Core\CoreInterface::ADDED,
                    [$sender, $senderName, $chatName, $usersAdded]
                );

                break;
        }


//        \Util::debug([$chatName, $message, $sender, $senderName, $usersAdded, $messageType]);


    }

    public function getMessageProp($id, $prop)
    {
        return $this->getProp('CHATMESSAGE ' . $id, $prop);
    }

    public function getProp($type, $prop)
    {
        $propAnswer = $this->getProxy()->Invoke('GET ' . $type . ' ' . strtoupper($prop));
        $result = explode($prop . ' ', $propAnswer);
        if (count($result) > 1) {
            return $result[1];
        } else {
            \Util::debug(['GET ' . $type . ' ' . $prop . ' ' . strtoupper($prop), $propAnswer]);
        }

        return '';
    }
}
