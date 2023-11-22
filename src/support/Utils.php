<?php

namespace Infira\Poesis\support;


class Utils
{
    /**
     * convert variable to array
     *
     * @param mixed $value
     * @return array
     */
    public static function flattenColumns(mixed $value): array
    {
        $result = [];
        foreach ((array)$value as $item) {
            if (is_array($item)) {
                array_push($result, ...self::flattenColumns($item));
                continue;
            }
            if (is_string($item) or is_numeric($item)) {
                foreach (explode(',', "$item") as $v) {
                    $v = trim($v);
                    if ($v != "") {
                        $result[] = $v;
                    }
                }
                continue;
            }
            $result[] = $item;

        }
        return $result;
    }

    /**
     * convert variable to array
     *
     * @param string|object|array|numeric $var
     * @param string $caseStringExplodeDelim - if the $var type is string then string is exploded to this param delimiter
     * @return array
     */
    public static function toArray($var, string $caseStringExplodeDelim = ","): array
    {
        if (is_object($var)) {
            return get_object_vars($var);
        }
        if (is_string($var) or is_numeric($var)) {
            $ex = explode($caseStringExplodeDelim, "$var");
            $r = [];
            if (is_array($ex)) {
                foreach ($ex as $v) {
                    $v = trim($v);
                    if ($v != "") {
                        $r[] = $v;
                    }
                }
            }

            return $r;
        }
        if (is_array($var)) {
            return $var;
        }

        return [];
    }

    /**
     * Simple string templating
     *
     * @param array $vars
     * @param string $string
     * @param array|null $defaultVars
     * @return string $string
     */
    public static function strVariables(array $vars, string $string, array $defaultVars = null): string
    {
        foreach ($vars as $name => $value) {
            $string = str_replace('{'.$name.'}', $value, $string);
        }
        if ($defaultVars) {
            $string = self::strVariables($defaultVars, $string, null);
        }

        return $string;
    }

    public static function isBetween(float $nr, float $from, float $to): bool
    {
        return ($nr >= $from and $nr <= $to);
    }

    /**
     * Dump variable
     *
     * @param $variable
     * @return string
     */
    public static function dump($variable): string
    {
        if (is_array($variable) or is_object($variable)) {
            return print_r($variable, true);
        }
        else {
            ob_start();
            var_dump($variable);

            return ob_get_clean();
        }
    }

    public static function getIP(): string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        }
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        }
        else if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        }
        else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        }
        else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        }
        else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }
        else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    public static function getCurrentUrl(): string
    {
        $url = 'http';
        if (isset($_SERVER['HTTPS'])) {
            $isHttps = strtolower($_SERVER['HTTPS']);
            if ($isHttps == 'on') {
                $url .= 's';
            }
        }

        return $url.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }

    public static function getBacktrace(int $startAt = 0): string
    {
        $backTrace = debug_backtrace();
        $until = 15;
        $trace = "<br />";
        $start = intval($startAt);
        $nr = 1;
        for ($i = $start; $i <= $until; $i++) {
            if (isset($backTrace[$i]['file'])) {
                $trace .= $nr.') File '.$backTrace[$i]['file'].' in line '.$backTrace[$i]['line'].'<br>';
                $nr++;
            }
        }

        return str_replace(getcwd(), "", $trace);
    }
}