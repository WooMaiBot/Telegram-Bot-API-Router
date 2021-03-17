<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use WooMaiLabs\TelegramBotAPI\Models\Update;

interface MiddlewareInterface
{
    public function __invoke(Update $update, array $params = []): Update|WebhookResponse;
}