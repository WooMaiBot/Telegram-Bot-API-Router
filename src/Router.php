<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use Exception;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\RequestInterface;
use WooMaiLabs\TelegramBotAPI\Models\CallbackQuery;
use WooMaiLabs\TelegramBotAPI\Models\Message;
use WooMaiLabs\TelegramBotAPI\Models\Update;

class Router
{
    protected $command_routes = [];
    protected $callback_routes = [];
    protected $text_routes = [];
    protected $text_default_route = null;
    protected $inline_routes = [];
    protected $inline_default_route = null;
    protected $global_middlewares = [];

    /**
     * Router constructor.
     * @param int $bot_user_id
     * @param string $bot_username
     */
    #[Pure] public function __construct(protected int $bot_user_id, string $bot_username)
    {
        $this->bot_username = ltrim($bot_username, '@');
    }

    public function command(array|Command $command, callable $callback, ...$middlewares)
    {
        $this->verifyMiddlewares($middlewares);

        if (is_array($command)) {
            $commands = [];
            foreach ($command as $cmd) {
                if ($cmd instanceof Command) {
                    $commands[] = $cmd;
                }
            }
        } else {
            $commands = [$command];
        }

        $route = new StandardRoute($callback, $middlewares);

        foreach ($commands as $c) {
            $this->command_routes[] = [$c, $route];
        }
    }

    public function callback(Callback $callback_identifier, callable $callback, ...$middlewares)
    {
        $this->verifyMiddlewares($middlewares);

        if (is_string($callback_identifier)) {
            $callback_identifier = new Callback($callback_identifier);
        }

        $this->callback_routes[] = [$callback_identifier, new StandardRoute($callback, $middlewares)];
    }

    /**
     * @param string $regex Pass empty string if this is a default route.
     * @param callable $callback
     * @param mixed ...$middlewares
     * @throws Exception
     */
    public function text(string $regex, callable $callback, ...$middlewares)
    {
        $this->verifyMiddlewares($middlewares);

        $route = new StandardRoute($callback, $middlewares);

        if (empty($regex)) {
            $this->text_default_route = $route;
        } else {
            $this->text_routes[] = [$regex, $route];
        }
    }

    /**
     * @param string $regex Pass empty string if this is a default route.
     * @param callable $callback
     * @param mixed ...$middlewares
     * @throws Exception
     */
    public function inline(string $regex, callable $callback, ...$middlewares)
    {
        $this->verifyMiddlewares($middlewares);

        $route = new StandardRoute($callback, $middlewares);

        if (empty($regex)) {
            $this->inline_default_route = $route;
        } else {
            $this->inline_routes[] = [$regex, $route];
        }
    }

    public function addGlobalMiddlewares(...$middlewares)
    {
        $this->verifyMiddlewares($middlewares);

        foreach ($middlewares as $middleware) {
            $this->global_middlewares[] = $middleware;
        }
    }

    public function route(RequestInterface $request = null, object $update_object = null): WebhookResponse
    {
        if ($request) {
            $update_object = json_decode($request->getBody()->read(1024 * 1024)); // Max 1 MiB
            if (!is_object($update_object)) {
                throw new Exception('JSON is malformed or too large in request body');
            }
        }

        $update = new Update($update_object);

        // message
        if (isset($update->message)) {
            $update->withAttribute('issued_user', $update->message->from);

            // check command
            foreach ($this->command_routes as $command_route) {
                /** @var Command $command */
                $command = $command_route[0];
                if ($this->matchCommand($command, $update)) {
                    /** @var StandardRoute $route */
                    $route = $command_route[1];
                    $route->prependMiddleware(...$this->global_middlewares);
                    return $route->call(
                        $update->withAttribute('route_type', 'command')->withAttribute('routed_command', $command),
                        $this->parseCommandParams($command_route[0], $update)
                    );
                }
            }

            $update->withAttribute('route_type', 'text');

            // text message regex
            $text = $this->getMessageText($update->message);
            foreach ($this->text_routes as $text_route) {
                if (preg_match($text_route[0], $text, $matches)) {
                    /** @var StandardRoute $route */
                    $route = $text_route[1];
                    $route->prependMiddleware(...$this->global_middlewares);
                    return $route->call($update, [$text, $matches]);
                }
            }

            // text message default
            if ($this->text_default_route) {
                /** @var StandardRoute $route */
                $route = $this->text_default_route;
                $route->prependMiddleware(...$this->global_middlewares);
                return $route->call($update, [$text, []]);
            }

            return new WebhookResponse();
        }

        // callback
        if (isset($update->callback_query)) {
            $update->withAttribute('route_type', 'callback')
                ->withAttribute('issued_user', $update->callback_query->from);

            foreach ($this->callback_routes as $callback_route) {
                if ($this->matchCallback($callback_route[0], $update->callback_query)) {
                    /** @var StandardRoute $route */
                    $route = $callback_route[1];
                    $route->prependMiddleware(...$this->global_middlewares);
                    return $route->call(
                        $update->withAttribute('routed_identifier', $callback_route[0]),
                        $this->parseCallbackDataParams($update->callback_query)
                    );
                }
            }

            return new WebhookResponse();
        }

        // inline
        if (isset($update->inline_query)) {
            $update->withAttribute('route_type', 'inline')
                ->withAttribute('issued_user', $update->inline_query->from);

            // inline regex
            foreach ($this->inline_routes as $inline_route) {
                if (preg_match($inline_route[0], $update->inline_query->query, $matches)) {
                    /** @var StandardRoute $route */
                    $route = $inline_route[1];
                    $route->prependMiddleware(...$this->global_middlewares);
                    return $route->call($update, [$update->inline_query->query, $matches]);
                }
            }

            // inline default
            if ($this->inline_default_route) {
                /** @var StandardRoute $route */
                $route = $this->inline_default_route;
                $route->prependMiddleware(...$this->global_middlewares);
                return $route->call($update, [$update->inline_query->query, []]);
            }

            return new WebhookResponse();
        }

        // other update types here ...

        return new WebhookResponse();
    }

    protected function verifyMiddlewares(array $middlewares)
    {
        foreach ($middlewares as $i => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new Exception("\$middlewares[$i] must be an instance of MiddlewareInterface");
            }
        }
    }

    protected function matchCommand(Command $command, Update $update): bool
    {
        $msgtext = $this->getMessageText($update->message);
        if (!$msgtext) {
            return false;
        }

        if (preg_match($this->getCommandRegex($command), $msgtext)) {
            return true;
        }

        return false;
    }

    protected function getMessageText(Message $message): ?string
    {
        if (isset($message->text)) {
            return $message->text;
        } else if (isset($message->caption)) {
            return $message->caption;
        } else {
            return null;
        }
    }

    protected function getCommandRegex(Command $command): string
    {
        $prefix = preg_quote($command->getPrefix() . $command->getCommand(), '/');

        $subcmds = [];
        foreach ($command->getSubcommands() as $subc) {
            $subcmds[] = preg_quote($subc, '/');
        }

        if (empty($subcmds)) {
            $subcmd = '';
        } else {
            $subcmd = '\s+' . implode('\s+', $subcmds);
        }

        return "/^$prefix(@$this->bot_username|)$subcmd($|\s)/i";
    }

    protected function parseCommandParams(Command $command, Update $update): array
    {
        $msgtext = $this->getMessageText($update->message);
        if (!$msgtext) {
            return [];
        }

        $param_string = trim(preg_replace($this->getCommandRegex($command), '', $msgtext));
        if (empty($param_string)) {
            return [];
        }

        return preg_split('/\s+/', $param_string);
    }

    protected function matchCallback(Callback $callback, CallbackQuery $query): bool
    {
        $identifier = urldecode(explode(',', $query->data)[0]);

        if (strcasecmp($identifier, $callback->getIdentifier()) === 0) {
            return true;
        }

        return false;
    }

    protected function parseCallbackDataParams(CallbackQuery $query): array
    {
        $p = explode(',', $query->data);
        unset($p[0]);

        $params = [];
        foreach ($p as $param) {
            $tmp = explode('=', $param, 2);
            $key = urldecode($tmp[0]);
            $val = $tmp[1] ? urldecode($tmp[1]) : null;
            $params[$key] = $val;
        }

        return $params;
    }
}
