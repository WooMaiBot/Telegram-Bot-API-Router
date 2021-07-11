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

    public function call(Update $update, array $params = []): WebhookResponse
    {
        return RouteChain::run($update, $this->callback, $this->middlewares, $params);
    }

    public function __invoke(Update $update): WebhookResponse
    {
        return $this->call($update);
    }
}