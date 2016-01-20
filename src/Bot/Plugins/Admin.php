<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\GroupAdminInterface;
use Bot\Plugins\Infrastructure\Message;
use Symfony\Component\Yaml\Yaml;

class Admin extends Message implements GroupAdminInterface
{
    const GROUP_REACTIVE = 'reactive';
    const GROUP_MODERATED = 'moderated';
    const GROUP_QUIZ = 'quiz';
    const GROUP_JENKINS = 'jenkins';

    const LOAD_ORDER = 0;

    /** @TODO: move to config/core */
    private static $groups = [
        self::GROUP_REACTIVE,
        self::GROUP_MODERATED,
        self::GROUP_QUIZ,
        self::GROUP_JENKINS,
    ];

    public function isAdmin($user) {
        return in_array($user, $this->pluginConfig['users']['admins']);
    }

    public function processMessage(
        $message,
        $sender,
        $senderName,
        $chatName,
        $selfHandle
    ) {
//        \Util::debug($sender);

        if (
           substr($message, 0, 2) === '!!'
            && strlen($message) > 2
            && in_array($sender, $this->pluginConfig['users']['admins'])
        ) {
            $arguments = explode(' ', mb_substr($message, 2));
            $command = array_shift($arguments);
            switch ($command) {
                case 'ре':
                case 're':
                case 'ht':
                case 'ку':
                    $this->ev->on(
                        'restart',
                        function () use ($chatName, $sender) {
                            $this->db['restart'] = [$chatName, time()];
                            $this->core->send(
                                $chatName,
                                'Секундочку...'
                            );
                            die("\n\n<<< RESTARTING BY REQUEST FROM " . $sender . "\n\n");
                        }
                    );
                    $this->ev->emit('restart');
                    break;

                case 'доб':
                    if (!count($arguments)) {
                        $arguments[] = 'reactive';
                    }
                    foreach ($arguments as $chatGroup) {
                        if (!in_array($chatGroup, self::$groups)) {
                            $this->core->send(
                                $chatName,
                                'Несуществующая группа ' . $chatGroup
                            );
                            continue;
                        }

                        $chats = $this->db['chats'] ?: [];
                        if (!array_key_exists($chatGroup, $chats)) {
                            $chats[$chatGroup] = [];
                        }

                        if (!in_array($chatName, $chats[$chatGroup])) {
                            $chats[$chatGroup][] = $chatName;
                            $chats[$chatGroup] = array_unique($chats[$chatGroup]);
                            $this->db['chats'] = $chats;

                            $this->core->send(
                                $chatName,
                                'Чат добавлен в группу ' . $chatGroup
                            );
                        } else {
                            $this->core->send(
                                $chatName,
                                'Чат уже в группе ' . $chatGroup
                            );
                        }
                    }

                    return false;

                    break;

                case 'удал':
                    if (!count($arguments)) {
                        $arguments[] = 'reactive';
                    }
                    foreach ($arguments as $chatGroup) {
                        $chats = $this->db['chats'] ?: [];
                        if (!array_key_exists($chatGroup, $chats)) {
                            $chats[$chatGroup] = [];
                        }

                        if (
                            in_array($chatName, $chats[$chatGroup])
                            && ($index = array_search($chatName, $chats[$chatGroup])) !== false
                        ) {
                            unset($chats[$chatGroup][$index]);
                            $this->db['chats'] = $chats;

                            $this->core->send(
                                $chatName,
                                'Чат удален из группы ' . $chatGroup
                            );
                        } else {
                            $this->core->send(
                                $chatName,
                                'Чат не в группе ' . $chatGroup
                            );
                        }
                    }

                    return false;

                    break;

                case 'пока':
                    try {
                        $this->core->send(
                            $chatName,
                            '/leave'
                        );
                    } catch (\Exception $e) {

                    }

                    return false;

                    break;

                case 'группы':
                    $groups = [];
                    $chats = $this->db['chats'] ?: [];
                    foreach ($chats as $chatGroup => $groupChats) {
                        if (in_array($chatName, $groupChats)) {
                            $groups[] = $chatGroup;
                        }
                    }
                    if (count($groups)) {
                        $this->core->send(
                            $chatName,
                            'Чат зарегистрирован в группах: ' . implode(', ', $groups)
                        );
                    } else {
                        $this->core->send(
                            $chatName,
                            'Чат не зарегистрирован в группах.'
                        );
                    }

                    return false;

                    break;

                case 'чат':
                    $this->core->send($chatName, $chatName);

                    return false;

                    break;

                case 'prop':
                    $this->core->send($chatName, $this->core->getProxy()->Invoke(implode(' ', $arguments)));

                    return false;

                    break;

                case 'конфиг':
                    try {
                        $config = Yaml::parse(file_get_contents(ROOT . DS . 'config' . DS . 'bot.yml'), true);
                        $this->container->set('config', $config);
                        $this->core->send($chatName, 'Конфиг перезагружен, а ты клевый ;)');
                    } catch (\Exception $e) {
                        $this->core->send($chatName, 'Не вышло: ' . $e->getMessage());
                    }

                    return false;

                    break;

                case 'деб':
                    ob_start();
                    try {
                        eval(implode(' ', $arguments));
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                    }
                    $this->core->send($chatName, ob_get_clean());

                    return false;

                    break;
            }
        }

        return true;
    }

    public static function registerGroup($chatGroup)
    {
        self::$groups[] = $chatGroup;
        self::$groups = array_unique(self::$groups);
    }

    public static function getGroups()
    {
        return self::$groups;
    }

    public static function isGroupRegistered($chatGroup)
    {
        return in_array($chatGroup, self::$groups);
    }
}
