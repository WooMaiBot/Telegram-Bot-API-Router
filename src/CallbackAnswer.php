<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use JetBrains\PhpStorm\Pure;
use WooMaiLabs\TelegramBotAPI\Models\CallbackQuery;

class CallbackAnswer extends WebhookResponse
{
    #[Pure]
    public function __construct(
        CallbackQuery $query,
        ?string $text = null,
        bool $show_alert = false,
        ?string $url = null,
        int $cache_time = 0,
    )
    {
        $data = [
            'callback_query_id' => $query->id,
        ];

        if ($text) {
            $data['text'] = $text;
        }

        if ($show_alert) {
            $data['show_alert'] = $show_alert;
        }

        if ($url) {
            $data['url'] = $url;
        }

        if ($cache_time) {
            $data['cache_time'] = $cache_time;
        }

        parent::__construct('answerCallbackQuery', $data);
    }
}