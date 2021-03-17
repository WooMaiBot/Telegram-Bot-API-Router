<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use WooMaiLabs\TelegramBotAPI\Models\Message;

class Reply extends WebhookResponse
{
    public function __construct(
        Message $message,
        string $text,
        bool $reply = true,
        ?string $parse_mode = null,
        bool $allow_sending_without_reply = true,
        bool $disable_web_page_preview = true,
        bool $disable_notification = false,
        array $additional_data = [],
    )
    {
        $data = [
            'chat_id' => $message->chat->id,
            'text' => $text,
            'allow_sending_without_reply' => $allow_sending_without_reply,
            'disable_web_page_preview' => $disable_web_page_preview,
            'disable_notification' => $disable_notification,
        ];

        if ($reply) {
            $data['reply_to_message_id'] = $message->message_id;
        }

        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }

        parent::__construct('sendMessage', array_merge($data, $additional_data));
    }
}