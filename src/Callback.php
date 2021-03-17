<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


class Callback
{
    public function __construct(protected string $identifier)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}