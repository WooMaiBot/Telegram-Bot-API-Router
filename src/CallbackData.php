<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use JetBrains\PhpStorm\Pure;

class CallbackData implements \Stringable
{
    public function __construct(protected string $identifier, protected array $data)
    {
    }

    #[Pure] public function toString(): string
    {
        $data = '';
        foreach ($this->data as $key => $val) {
            $key = urlencode($key);
            $val = urlencode($val);
            $data .= "$key=$val,";
        }

        $identifier = urlencode($this->identifier);

        return rtrim("$identifier,$data", ',');
    }

    #[Pure] public function __toString(): string
    {
        return $this->toString();
    }
}