<?php
namespace Bot\Plugins;

use Bot\Core\CoreInterface;
use Bot\Filebase\Factory;
use Bot\Filebase\Wrapper;
use Bot\Plugins\Infrastructure\Plugin;
use DI\Annotation\Inject;

/**
 * Class QuizServer
 * @package Bot\Plugins
 * @TODO i18n
 */
class QuizServer extends Plugin
{
    const LOAD_ORDER = 200;
    const PERIOD = 1;
    const TO_HINT = 20;
    const TIMEOUT = 60;
    const ROUNDS = 10;
    const CHATGROUP = 'quiz';
    const QUIZ_DB_PATH = DATA . DS . 'questions.txt';

    protected $currentTime = 0;

    protected $questionsCount = 0;

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
     * @var Admin
     */
    protected $admin;

    /**
     * @var Wrapper
     */
    protected $quizDb;


    public function init()
    {
        Admin::registerGroup(self::CHATGROUP);

        $this->questionsCount = \Util::getLinesCount(self::QUIZ_DB_PATH);
        $this->quizDb = Factory::create(\Util::getLocalPath() . DS . 'quiz.json');
        if (!isset($this->quizDb['scores'])) {
            $this->quizDb['scores'] = [];
        }

        $this->ev->on(CoreInterface::MESSAGE, [$this, 'processMessage']);
        $this->ev->on('tick', [$this, 'processRunningQuiz']);

        \Util::console('Quiz: Questions count: ' . $this->questionsCount);

        $this->ev->on(
            'restart',
            function () {
                $running = \Util::store('quiz') ?: [];
                if (count($running)) {
                    foreach ($running as $chatName => $run) {
                        $msg = '(devil) Игра окончена, т.к. ведущий перезагружается.';
                        $msg .= $this->scoresMessage($chatName);
                        $this->core->send($chatName, $msg);

                        unset($running[$chatName]);
                        \Util::store('quiz', $running);
                    }
                }

                return true;
            }
        );
    }

    public function processMessage(
        $message,
        $sender,
        $senderName,
        $chatName,
        $receivedTime
    ) {
//        \Util::debug(self::CHATGROUP);

        $chats = $this->db['chats'] ?: [];
        if (isset($chats[self::CHATGROUP]) && in_array($chatName, $chats[self::CHATGROUP])) {
            $running = \Util::store('quiz') ?: [];

            if (substr($message, 0, 1) === '!' && strlen($message) > 1) {
                $arguments = explode(' ', mb_substr($message, 1));
                $command = array_shift($arguments);
//                \Util::debug('Quiz: ' . $command . ' ' . implode(' ', $arguments) . ' in ' . $chatName);
                switch ($command) {
                    case 'вик':
                        $this->startQuiz($chatName, $sender, $senderName);
                        break;

                    case 'топ':
                        $msg = 'Топ-10 игроков всех времен:' . PHP_EOL;

                        $globalScores = $this->quizDb['scores'];
                        if (array_key_exists($chatName, $globalScores)) {
                            uksort(
                                $globalScores[$chatName],
                                function ($key1, $key2) use ($globalScores, $chatName) {
                                    return $globalScores[$chatName][$key2] - $globalScores[$chatName][$key1];
                                }
                            );

                            $i = 1;
                            foreach ($globalScores[$chatName] as $sender => $score) {
                                $msg .= "{$i}. {$sender} - {$score}" . PHP_EOL;
                                if ($i >= 10) {
                                    break;
                                }
                                $i++;
                            }

                            $this->core->send($chatName, $msg);
                        }

                        break;

                    case 'д':
                    case 'дальше':
                        if (isset($running[$chatName])) {
                            $quiz = $running[$chatName]['quiz'];
                            $msg = "(mp) Никто не угадал слово \"{$quiz['answer']}\", всего попыток: {$quiz['tries']}";
                            $msg .= $this->nextRoundMessage($quiz, $chatName);
                            $this->core->send($chatName, $msg);
                        }
                        break;
                }
            }

            if (isset($running[$chatName])) {
                $quiz = $running[$chatName]['quiz'];
                $scores = $running[$chatName]['scores'];
                if (
                    mb_strtolower(trim($message)) === $quiz['answer']
                    || preg_match(
                        '/(?:^|\s+)' . preg_quote($quiz['answer'], '/') . '[\?,\.\!\:\-\s]*/iu',
                        mb_strtolower(trim($message))
                    )
                ) {
                    $globalScores = $this->quizDb['scores'];
                    if (!isset($globalScores[$chatName])) {
                        $globalScores[$chatName] = [];
                    }
                    if (!isset($globalScores[$chatName][$sender])) {
                        $globalScores[$chatName][$sender] = 0;
                    }
                    $globalScores[$chatName][$sender]++;
                    $this->quizDb['scores'] = $globalScores;

                    if (!isset($scores[$sender])) {
                        $scores[$sender] = [0, $senderName];
                    }
                    $scores[$sender][0]++;
                    $running[$chatName]['scores'] = $scores;
                    \Util::store('quiz', $running);

                    $msg = "(sun) {$senderName} угадывает слово \"{$quiz['answer']}\"! (highfive)";
                    $msg .= $this->nextRoundMessage($quiz, $chatName);
                    $this->core->send($chatName, $msg);

                    return false;
                } else {
                    $quiz['tries']++;
                    if (mt_rand(1, 100) >= 99) {
                        $this->core->send($chatName, 'Сам ты "' . $message . '", ' . $senderName);
                    } elseif (mt_rand(1, 100) >= 99) {
                        $this->core->send($chatName, '"' . $message . '" у тебя в штанах, ' . $senderName);
                    }
                    $running[$chatName]['quiz'] = $quiz;
                    \Util::store('quiz', $running);
                }
            }
        } else {
            if (mb_substr($message, 0, 1) === '!' && mb_strlen($message) > 1) {
                $arguments = explode(' ', mb_substr($message, 1));
                $command = array_shift($arguments);
//                \Util::debug('Quiz: ' . $command . ' ' . implode(' ', $arguments) . ' in ' . $chatName);
                switch ($command) {
                    case 'вик':
                        $this->core->send($chatName, 'Пройдемте-с: ' . $this->pluginConfig['quizChat']);

                        return false;
                        break;
                }

            }
        }

        return true;
    }

    public function processRunningQuiz($time)
    {
        if ($time >= $this->currentTime + self::PERIOD) {
            $this->currentTime = $time;
            $running = \Util::store('quiz') ?: [];

            if (count($running)) {
                foreach ($running as $chatName => $vars) {
                    $quiz = $vars['quiz'];
                    if ($quiz['round'] <= self::ROUNDS && $quiz['started'] + self::TIMEOUT >= $time) {
                        if (!$quiz['hint'] && $quiz['started'] < $time - self::TO_HINT) {
                            $hint = $this->getQuizHint($quiz['answer']);
                            $this->core->send($chatName, '(nerd) Подсказка: ' . $hint);
                            $quiz['hint'] = true;
                            $running[$chatName]['quiz'] = $quiz;
                            \Util::store('quiz', $running);

                        }

                    } elseif ($quiz['round'] === self::ROUNDS && $quiz['started'] < $time - self::TIMEOUT) {
                        $msg = 'Это был последний раунд, никто не угадал слово "' . $quiz['answer'] . '".';
                        $msg .= $this->scoresMessage($chatName);
                        $this->core->send($chatName, $msg);

                        unset($running[$chatName]);
                        \Util::store('quiz', $running);


                    } else {
                        if ($quiz['tries']) {
                            $msg = "(mp) Никто не угадал слово \"{$quiz['answer']}\", всего попыток: {$quiz['tries']}";
                            $msg .= $this->nextRoundMessage($quiz, $chatName);
                            $this->core->send($chatName, $msg);

                        } else {
                            $msg = "(mp) Никто не угадал слово \"{$quiz['answer']}\"" . PHP_EOL;
                            $msg .= "Преждевременная эвакуация за отсутствием игроков.";
                            $msg .= $this->scoresMessage($chatName);
                            $this->core->send($chatName, $msg);

                            unset($running[$chatName]);
                            \Util::store('quiz', $running);

                        }
                    }


                }
            }
        }
    }

    private function startQuiz($chatName, $sender, $senderName)
    {
        $chats = $this->db['chats'] ?: [];
        if (isset($chats[self::CHATGROUP]) && in_array($chatName, $chats[self::CHATGROUP])) {
//            \Util::debug('Quiz: start request in ' . $chatName);

            $running = \Util::store('quiz') ?: [];

            if (!isset($running[$chatName])) {
                \Util::console('Quiz: Starting quiz in ' . $chatName);

//                $scores = $this->quizDb['scores'];
//                $score = 0;
//                if (isset($scores[$chatName]) && isset($scores[$chatName][$sender])) {
//                    $score = $scores[$chatName][$sender];
//                }

                $quiz = $this->createRound(1);
//                \Util::debug($quiz);

                $msg = '(mp) В моих чертогах разума ' . $this->questionsCount . ' каверзных вопроса. Вызов принят, ' . $senderName . '!' . PHP_EOL;
                $msg .= '(mp) Первый раунд, вопрос №' . $quiz['number'] . ':' . PHP_EOL . $quiz['question'] . ' (' . mb_strlen(
                        $quiz['answer']
                    ) . ' букв)';
                $this->core->send($chatName, $msg);

                $running[$chatName]['quiz'] = $quiz;
                $running[$chatName]['scores'] = [];
                \Util::store('quiz', $running);
//                \Util::debug('Quiz: not running in ' . $chatName);

            } else {
//                \Util::debug('Quiz: running in ' . $chatName);

                $this->core->send(
                    $chatName,
                    "Сорян, {$senderName}, но твоя принцесса в другом замке! Дождись конца текущей игры."
                );
            }
        }
    }

    private function getQuestion()
    {
        $num = mt_rand(0, \Util::getLinesCount(self::QUIZ_DB_PATH));
        $buffer = \Util::getFileLine(self::QUIZ_DB_PATH, $num);

        if ($buffer) {
            $question = explode('*', trim($buffer));
            $question[] = $num;

            return $question;
        }

        throw new \Exception('Quiz: Got no question');
    }

    private function getQuizHint($answer)
    {
//        return $answer;
        $wordLength = mb_strlen($answer);
        $word = [];
        for ($i = 0; $i < $wordLength; $i++) {
            $word[] = mb_substr($answer, $i, 1);
        }
        $hint = str_split(str_pad('', $wordLength, '*'));

        switch ($wordLength) {
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
                $hintLength = 1;
                break;
            case 6:
            case 7:
            case 8:
            case 9:
                $hintLength = 2;
                break;
            case 10:
            case 11:
            case 12:
            case 13:
                $hintLength = 3;
                break;
            default:
                $hintLength = 4;
                break;
        }

        $chars = [];
        do {
            $hintLength--;

            $char = mt_rand(0, $wordLength - 1);
            while (in_array($char, $chars)) {
                $char = mt_rand(0, $wordLength - 1);
            }
            $chars[] = $char;
        } while ($hintLength);

        foreach ($chars as $char) {
            $hint[$char] = $word[$char];
        }

        $hint = implode('', $hint);

        return iconv("UTF-8", "UTF-8//IGNORE", $hint);
    }

    private function createRound($roundNumber = 1)
    {
        list($question, $answer, $number) = $this->getQuestion();

        return [
            'round'    => $roundNumber,
            'started'  => time(),
            'question' => $question,
            'answer'   => $answer,
            'number'   => $number,
            'hint'     => false,
            'tries'    => 0,
        ];
    }

    public function nextRoundMessage($quiz, $chatName)
    {
        $msg = PHP_EOL;
        $running = \Util::store('quiz') ?: [];
        if ($quiz['round'] < self::ROUNDS) {
            $quiz = $this->createRound(++$quiz['round']);
            $running[$chatName]['quiz'] = $quiz;
            $msg .= '(mp) Раунд ' . $quiz['round'] . ', вопрос №' . $quiz['number'] . ':' . PHP_EOL . $quiz['question'] . ' (' . mb_strlen(
                    $quiz['answer']
                ) . ' букв)';
        } else {
            $msg .= 'Это был последний раунд, все молодцы! (cool) ';
            $msg .= $this->scoresMessage($chatName);
            unset($running[$chatName]);
        }
        \Util::store('quiz', $running);

        return $msg;
    }

    private function scoresMessage($chatName)
    {
        $msg = PHP_EOL . 'Итоги игры:' . PHP_EOL;
        $scores = \Util::store('quiz')[$chatName]['scores'] ?: [];

//        \Util::debug(\Util::store('quiz')[$chatName]);
        if (count($scores)) {
            $globalScores = $this->quizDb['scores'];

            uksort(
                $scores,
                function ($key1, $key2) use ($scores) {
                    return $scores[$key2][0] - $scores[$key1][0];
                }
            );

            $i = 1;
            foreach ($scores as $sender => $score) {
                list($score, $senderName) = $score;
                $globalScore = 0;
                if (isset($globalScores[$chatName], $globalScores[$chatName][$sender])) {
                    $globalScore = $globalScores[$chatName][$sender];
                }
                $msg .= "{$i}. {$senderName} - {$score} ({$globalScore})" . PHP_EOL;
            }
        } else {
            $msg .= 'Тлен и пустота. (rain) ';
        }

        return $msg;
    }
}
