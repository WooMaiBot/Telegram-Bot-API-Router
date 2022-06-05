<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use WooMaiLabs\TelegramBotAPI\Models\Update;
use WooMaiLabs\TelegramBotAPI\Router\Utils\CallableIdentifier;

class StandardRoute
{
    public static function dummy(): StandardRoute
    {
        return new static(function () {
            return new WebhookResponse();
        }, []);
    }

    protected $callback;
    protected $route_dest;

    /**
     * StandardRoute constructor.
     * @param callable $callback
     * @param array $middlewares
     */
    public function __construct(callable $callback, protected array $middlewares)
    {
        $this->callback = $callback;
        $this->route_dest = CallableIdentifier::get($callback);
    }

    public function prependMiddleware(...$middlewares)
    {
        array_unshift($this->middlewares, ...$middlewares);
    }

    public function call(Update $update, array $params = []): WebhookResponse
    {
        $update->withAttribute('route_destination', $this->route_dest);
        return RouteChain::run($update, $this->callback, $this->middlewares, $params);
    }

    public function __invoke(Update $update): WebhookResponse
    {
        return $this->call($update);
    }
}
