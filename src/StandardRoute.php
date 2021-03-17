<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use WooMaiLabs\TelegramBotAPI\Models\Update;

class StandardRoute
{
    protected $callback;

    /**
     * StandardRoute constructor.
     * @param callable $callback
     * @param array $middlewares
     */
    public function __construct(callable $callback, protected array $middlewares)
    {
        $this->callback = $callback;
    }

    public function prependMiddleware(...$middlewares)
    {
        array_unshift($this->middlewares, ...$middlewares);
    }

    protected function handleMiddleware(Update $update, array $params): Update|WebhookResponse
    {
        foreach ($this->middlewares as $middleware) {
            $tmp = $middleware($update, $params);

            if ($tmp instanceof Update) {  // double check
                $update = $tmp;
            } else if ($tmp instanceof WebhookResponse) {
                return $tmp;
            }
        }

        return $update;
    }

    public function call(Update $update, array $params = []): WebhookResponse
    {
        $result = $this->handleMiddleware($update, $params);
        if ($result instanceof WebhookResponse) {
            return $result;
        } else if ($result instanceof Update) {
            $update = $result;
        }

        $response = ($this->callback)($update, $params);
        if (!$response instanceof WebhookResponse) {
            $response = new WebhookResponse();
        }

        return $response;
    }

    public function __invoke(Update $update): WebhookResponse
    {
        return $this->call($update);
    }
}