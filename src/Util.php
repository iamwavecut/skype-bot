<?php

//use IniParser;
//use Filebase\Filebase;

/**
 * Class Bot
 * @TODO i18n
 */
class Util
{
    const STORE = '.skypebot';

    public static $registry = [];
    public static $linux = (PHP_OS === 'Linux');
    public static $debug;

    /**
     * @param string $key
     * @param null $value
     * @return mixed
     */
    public static function store($key, $value = null)
    {
        if (!array_key_exists($key, self::$registry) && $value === null) {
//            self::debug("Undefined registry item '{$key}'");

            return null;
        }

        return $value === null ? self::$registry[$key] : self::$registry[$key] = $value;
    }

    /**
     * @param string $key
     */
    public static function purge($key)
    {
        unset(self::$registry[$key]);
    }

    public static function getLocalPath()
    {
        $home = self::$linux ? posix_getpwuid(posix_getuid())['dir'] : ($_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH']);
        $storeDir = $home . DS . self::STORE;
//        self::debug('STOREDIR ' .$storeDir);
        if (!is_dir($storeDir)) {
            mkdir($storeDir, 0777, true);
        }

        return $storeDir;
    }

    public static function console($message)
    {
        if ($message) {
            $print = print_r($message, true);
            echo $print . PHP_EOL;
        }
    }

    public static function debug($message)
    {
        if (self::$debug) {
            self::console($message);
        }
    }

    public static function humanFileSize($size, $unit = null)
    {
        if ((!$unit && $size >= 1 << 30) || $unit === 'GB') {
            return number_format($size / (1 << 30), 2) . 'GB';
        }
        if ((!$unit && $size >= 1 << 20) || $unit === 'MB') {
            return number_format($size / (1 << 20), 2) . 'MB';
        }
        if ((!$unit && $size >= 1 << 10) || $unit === 'KB') {
            return number_format($size / (1 << 10), 2) . 'KB';
        }

        return number_format($size) . ' bytes';
    }

    public static function toUTF($string)
    {
        return mb_convert_encoding($string, 'UTF-8', 'cp1251');
    }

    public static function writtenNum($number, $words, $gender = 'female')
    {
        $get_digit = function ($number, $digit) {
            # Получение разряда числа
            $up = pow(10, $digit);
            $down = pow(10, $digit - 1);

            return ($number >= $down)
                ? floor(($number % $up) / $down)
                : 0;
        };

        # Возвращает число прописью в именительном падеже
        # (используется для товарных чеков).
        # $gender: female|male|middle

        if (!is_array($words)) {
            $words = explode(',', $words);
        }

        $str = '';

        $names = [
            1 => 'тысяча,тысячи,тысяч',
            'миллион,миллиона,миллионов',
            'миллиард,миллиарда,миллиардов',
            // сюда добавить по желанию
        ];

        $F = __FUNCTION__;

        foreach (array_reverse($names, true) as $i => $w) {

            $pow = pow(1000, $i);

            if ($number >= $pow) {
                $str .= self::writtenNum(
                            floor($number / $pow),
                            $w,
                            (($i === 1) ? 'female' : 'male')
                        ) . ' ';

                $number = $number % $pow;
            }
        }


        # Сотни

        if ($number >= 100) {
            $hundreds = [
                1 => 'сто',
                'двести',
                'триста',
                'четыреста',
                'пятьсот',
                'шестьсот',
                'семьсот',
                'восемьсот',
                'девятьсот',
            ];
            $h = $get_digit($number, 3);
            if (isset($hundreds[$h])) {
                $str .= "$hundreds[$h] ";
            }
        }


        # Десятки

        $d = $get_digit($number, 2);

        if ($d >= 2 OR $d == 0) {
            $decs = [
                2 => 'двадцать',
                'тридцать',
                'сорок',
                'пятьдесят',
                'шестьдесят',
                'семьдесят',
                'восемьдесят',
                'девяносто',
            ];
            if (isset($decs[$d])) {
                $str .= "$decs[$d] ";
            }

            # Единицы

            $u = $get_digit($number, 1);

            if ($u > 2) {
                $units = [
                    3 => 'три',
                    'четыре',
                    'пять',
                    'шесть',
                    'семь',
                    'восемь',
                    'девять',
                ];
                $str .= "$units[$u] "
                        . (
                        ($u > 4)
                            ? $words[2]
                            : $words[1]
                        );
            } elseif ($u == 2) {
                $tmp = [
                    'female' => 'две',
                    'male'   => 'два',
                    'middle' => 'два',
                ];
                $str .= "$tmp[$gender] $words[1]";
            } elseif ($u == 1) {
                $tmp = [
                    'female' => 'одна',
                    'male'   => 'один',
                    'middle' => 'одно',
                ];
                $str .= "$tmp[$gender] $words[0]";
            } else {
                $str .= $words[2];
            } // ноль

        } else {

            $sub_d = $number % 100;

            $tmp = [
                10 => 'десять',
                'одиннадцать',
                'двенадцать',
                'тринадцать',
                'четырнадцать',
                'пятнадцать',
                'шестнадцать',
                'семнадцать',
                'восемьнадцать',
                'девятнадцать',
            ];
            $str .= "$tmp[$sub_d] $words[2]";
            unset($tmp);
        }

        return $str;
    }

    public static function getConst($className, $const)
    {
        return (new \ReflectionClass($className))->getConstant($const);
    }

    public static function getRandomGreeting($name) {
        $greets = [];
        foreach (file(ROOT . DS . 'data' . DS . 'greets.txt') as $greetLine) {
            $greets[] = trim($greetLine);
        }

        $greet = $greets[mt_rand(0, count($greets) - 1)];
        $greet = str_replace('%username%', $name, $greet);

        return $greet;
    }

    /**
     * @param string $filePath
     * @return int|null
     */
    public static function getLinesCount($filePath) {
        if (!file_exists($filePath)) {
            self::debug('File not found: ' . $filePath);

            return null;
        }

        if (Util::$linux) {
            $count = exec('wc -l ' . $filePath);
            $count = (int)substr($count, 0, strpos($count, ' '));
        } else {
            $count = (int)exec('FINDSTR /R /N "^.*" ' . $filePath . ' | FIND /C ":"');
            $count++;
        }

        return $count;
    }

    /**
     * @param string $filePath
     * @return string|null
     */
    public static function getRandomLine($filePath) {
        if (!file_exists($filePath)) {
            self::debug('File not found: ' . $filePath);

            return false;
        }

        $rewind = mt_rand(0, self::getLinesCount($filePath));

        $fileHandle = fopen($filePath, 'r');
        $result = null;
        while ((--$rewind > 0) && (($buffer = fgets($fileHandle, 2048)) !== false)) {
        }
        fclose($fileHandle);

        return $result;
    }

    /**
     * @param string $filePath
     * @param int $line
     * @return string|null
     */
    public static function getFileLine($filePath, int $line) {
        if (!file_exists($filePath)) {
            self::debug('File not found: ' . $filePath);

            return false;
        }

        $fileHandle = fopen($filePath, 'r');
        $result = null;
        while ((--$line > 0) && (($buffer = fgets($fileHandle, 2048)) !== false)) {
        }
        fclose($fileHandle);

        return $result;
    }
}

