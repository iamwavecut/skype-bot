<?php
namespace Bot\Core;

use DI\Annotation\Inject;
use DI\Container;
use ISkype;
use Sabre\Event\EventEmitter;

/**
 * THIS CLASS IS DISCONTINUED BECAUSE OF FUNDAMENTAL INCOMPARTIBILITY WITH CURRENT IMPLEMENTATION
 *
 * Class Windows
 * @package Bot\Core
 * @deprecated
 */
class Windows extends ISkype implements CoreInterface
{
    const TYPE_UNKNOWN = -1;
    const TYPE_CREATED = 0;
    const TYPE_ADDED = 2;
    const TYPE_TOPIC = 3;
    const TYPE_MESSAGE = 4;
    const TYPE_LEFT = 5;

    /** @var \COM */
    protected $com;
    protected $sink;

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
        if (!extension_loaded('com_dotnet')) {
            throw new \Exception('No COM extension loaded. Exiting.');
        }
        $this->com = new \COM("Skype4COM.Skype");
        com_event_sink($this->com, $this, 'ISkype');
        if (!$this->com->client()->isRunning()) {
            $this->com->client()->start(/*true, true*/);
        }
        $this->com->Attach(5, false);
        com_message_pump(1000);
    }

    public function OnlineStatus(&$pUser, $Status)
    {
        print "Status: $pUser->Handle $Status\n";
    }

    public function MessageStatus(&$pMessage, $Status)
    {
        $selfHandle = $this->com->CurrentUser->Handle;
        $msgType = (int)$pMessage->Type;
        $sender = $pMessage->FromHandle;
        $chatName = $pMessage->ChatName;
        \Util::console($msgType);

        if ($msgType === self::TYPE_ADDED) {
            $addedUsers = [];
            foreach ($pMessage->Users as $User) {
                $addedUsers[$User->Handle] = $username = ($User->DisplayName ?: ($User->FullName ?: ($User->Handle)));
            }

            $this->ev->emit(self::ADDED, [$chatName, $addedUsers, $sender, $selfHandle]);

        } elseif ($msgType === self::TYPE_MESSAGE || $msgType === self::TYPE_UNKNOWN) {
            $message = (string)$pMessage->Body;
            $senderName = $pMessage->FromDisplayName;

            $this->ev->emit(self::MESSAGE, [$message, $sender, $senderName, $chatName, $selfHandle]);
        }
    }

    public function poll($timeout = 100)
    {
        \com_message_pump($timeout);
    }

    public function send($targetId, $message)
    {
        // TODO: Implement send() method.
    }

    public function getProxy()
    {
        // TODO: Implement getProxy() method.
    }

    public function getProp($type, $prop)
    {
        // TODO: Implement getProp() method.
    }
}

