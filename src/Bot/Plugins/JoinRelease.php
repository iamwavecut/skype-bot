<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Timer;

class JoinRelease extends Timer
{
    const PERIOD = 5;

    public function init()
    {
        $this->ev->on('tick', [$this, 'processJoiners']);
    }

    public function processJoiners($time)
    {
        if ($time >= $this->currentTime + self::PERIOD) {
            $this->currentTime = $time;
            $joiners = $this->db['joiners'] ?: [];
            foreach ($joiners as $chat => $users) {
                foreach ($users as $user => $joinTime) {
                    if ($joinTime + 15 <= $time) {
                        try {
                            $this->core->send($chat, "/setrole {$user} user");
                        } catch (\Exception $e) {
                            // all good
                        }
                        $displayName = $this->core->getProp('USER ' . $user, 'DISPLAYNAME');
                        $fullName = $this->core->getProp('USER ' . $user, 'FULLNAME');
                        $name = ($displayName ?: ($fullName ?: ($user)));
                        $greet = \Util::getRandomGreeting($name);
                        $this->core->send($chat, $greet);

                        unset($joiners[$chat][$user]);
                        $this->db['joiners'] = $joiners;
                    }
                }
            }
        }
    }

}
