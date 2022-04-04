<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


class WebhookResponse
{
    public function __construct(protected ?string $method = null, protected array $data = [])
    {
    }

    public function isEmpty(): bool
    {
        return empty($this->method);
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        if (!$this->method) {
            return [];
        } else {
            $data = $this->data;
            $data['method'] = $this->method;
            return $data;
        }
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
