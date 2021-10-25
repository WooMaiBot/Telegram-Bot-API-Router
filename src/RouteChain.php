<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use WooMaiLabs\TelegramBotAPI\Models\Update;

class RouteChain
{
    /**
     * @param \WooMaiLabs\TelegramBotAPI\Models\Update $update
     * @param callable $handler
     * @param MiddlewareInterface[] $middlewares
     * @param array $params
     * @return WebhookResponse
     */
    public static function run(Update $update, callable $handler, array $middlewares, array $params): WebhookResponse
    {
        $first_middleware = array_shift($middlewares);
        $next = new self($handler, $middlewares, $params);
        if (is_callable($first_middleware)) {
            return $first_middleware($update, $params, $next);
        } else {
            return $next($update);
        }
    }

    /**
     * RouteChain constructor.
     * @param callable $handler
     * @param MiddlewareInterface[] $middleware_chain
     * @param array $params
     */
    public function __construct(protected mixed $handler, protected array $middleware_chain, protected array $params)
    {
    }

    public function __invoke(Update $update): WebhookResponse
    {
        $next = array_shift($this->middleware_chain);
        if (is_callable($next)) {
            return $next($update, $this->params, $this);
        }

        $backward_rsp = ($this->handler)($update, $this->params);
        return $backward_rsp instanceof WebhookResponse ? $backward_rsp : new WebhookResponse();
    }
}
