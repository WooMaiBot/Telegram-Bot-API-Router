<?php


namespace WooMaiLabs\TelegramBotAPI\Router\Utils;


class CallableIdentifier
{
    public static function get(callable $callable): string
    {
        if (is_string($callable)) {
            return $callable;
        } else if (is_array($callable)) {
            if (is_object($callable[0])) {
                $class_name = get_class($callable[0]);
            } else {
                $class_name = $callable[0];
            }
            return "$class_name::$callable[1]";
        } else if (is_object($callable)) {
            return get_class((object)$callable);
        } else {
            return "OTHER_CALLABLE";
        }
    }
}
