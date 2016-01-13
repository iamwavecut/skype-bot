<?php
namespace Bot\Plugins;

use Bot\Core\CoreInterface;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

class JoinGatekeeper extends Plugin
{
    const LOAD_ORDER = 10;
    const REACTION_TYPE = CoreInterface::ADDED;
    const CHATGROUP = 'moderated';

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

    public function init($reactionMethod = 'processJoin')
    {
        $this->ev->on(CoreInterface::ADDED, [$this, $reactionMethod]);
        $this->ev->on(CoreInterface::KICKED, [$this, 'unJoin']);

        Admin::registerGroup(self::CHATGROUP);

    }

    /**
     * @param string $sender
     * @param string $senderName
     * @param string $chatName
     * @param array $usersAdded
     */
    public function processJoin($sender, $senderName, $chatName, $usersAdded)
    {
//        \Util::debug([$sender, $senderName, $chatName, $usersAdded]);

        if (
            array_key_exists(Admin::GROUP_MODERATED, $this->db['chats'])
            && in_array($chatName, $this->db['chats'][Admin::GROUP_MODERATED])
        ) {
            $joiners = $this->db['joiners'] ?: [];
            if (!array_key_exists($chatName, $joiners)) {
                $joiners[$chatName] = [];
            }

            foreach ($usersAdded as $user) {
                $joiners[$chatName][$user] = time();
                try {
                    $this->core->send($chatName, "/setrole {$user} listener");
                } catch (\Exception $e) {
                    // all good
                } finally {
//                $this->core->send(
//                    $chatName,
//                    "Привет, {$user}. В чате действует предмодерация входа, поэтому ты не сможешь писать еще две минуты."
//                );
                }
            }
            $this->db['joiners'] = $joiners;
        }
    }

    /**
     * @param string $sender
     * @param string $senderName
     * @param string $chatName
     * @param array $usersKicked
     */
    public function unJoin($sender, $senderName, $chatName, $usersKicked)
    {
        $joiners = $this->db['joiners'] ?: [];
        if (!array_key_exists($chatName, $joiners)) {
            $joiners[$chatName] = [];
        }

        foreach ($usersKicked as $user) {
            if (array_key_exists($user, $joiners[$chatName])) {
                unset($joiners[$chatName][$user]);
            }
        }
        $this->db['joiners'] = $joiners;
    }
}
