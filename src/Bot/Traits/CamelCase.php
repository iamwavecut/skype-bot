<?php

namespace Bot\Traits;

trait CamelCase
{
    /**
     * @param $string
     * @return string
     */
    private function toUnderscore($string)
    {
        $string = preg_replace("/(?<=\\w)(?=[A-Z])/", "_$1", $string);

        return strtolower($string);
    }

    /**
     * @param $string
     * @return string
     */
    private function toCamelCase($string)
    {
        return implode(
            '',
            array_map(
                function ($part) {
                    return ucfirst(strtolower($part));
                },
                explode('_', $string)
            )
        );
    }
}
