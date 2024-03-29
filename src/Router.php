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
    protected array $command_routes = [];
    protected array $callback_routes = [];
    protected array $text_routes = [];
    protected array $text_default_routes = [];
    protected array $inline_routes = [];
    protected array $inline_default_routes = [];
    protected array $global_middlewares_prepend = [];
    protected array $global_middlewares_append = [];
    protected array $catch_all_middlewares_prepend = [];
    protected array $catch_all_middlewares_append = [];

    /**
     * Router constructor.
     * @param int $bot_user_id
     * @param string $bot_username
     */
    #[Pure]
    public function __construct(protected int $bot_user_id, string $bot_username)
    {
        $this->bot_username = ltrim($bot_username, '@');
    }

    public function command(array|Command $command, callable $callback, ...$middlewares): void
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

    public function callback(Callback|string $callback_identifier, callable $callback, ...$middlewares): void
    {
        $this->verifyMiddlewares($middlewares);

        if (is_string($callback_identifier)) {
            $callback_identifier = new Callback($callback_identifier);
        }

        $this->callback_routes[] = [$callback_identifier, new StandardRoute($callback, $middlewares)];
    }

    /**
     * @param string|null $regex Pass empty string if this is a default route.
     * @param callable $callback
     * @param mixed ...$middlewares
     * @throws Exception
     */
    public function text(?string $regex, callable $callback, ...$middlewares): void
    {
        $this->verifyMiddlewares($middlewares);

        $route = new StandardRoute($callback, $middlewares);

        if (empty($regex)) {
            $this->text_default_routes[] = $route;
        } else {
            $this->text_routes[] = [$regex, $route];
        }
    }

    /**
     * @param string|null $regex Pass empty string if this is a default route.
     * @param callable $callback
     * @param mixed ...$middlewares
     * @throws Exception
     */
    public function inline(?string $regex, callable $callback, ...$middlewares): void
    {
        $this->verifyMiddlewares($middlewares);

        $route = new StandardRoute($callback, $middlewares);

        if (empty($regex)) {
            $this->inline_default_routes[] = $route;
        } else {
            $this->inline_routes[] = [$regex, $route];
        }
    }

    const PREPEND = 1;
    const APPEND = 2;

    public function addGlobalMiddlewares(int $order, ...$middlewares): void
    {
        $this->verifyMiddlewares($middlewares);

        if ($order === self::PREPEND) {
            $this->global_middlewares_prepend = array_merge($this->global_middlewares_prepend, $middlewares);
        } else {
            $this->global_middlewares_append = array_merge($this->global_middlewares_append, $middlewares);
        }

    }

    public function addCatchAllMiddlewares(int $order, ...$middlewares): void
    {
        $this->verifyMiddlewares($middlewares);

        if ($order === self::PREPEND) {
            $this->catch_all_middlewares_prepend = array_merge($this->catch_all_middlewares_prepend, $middlewares);
        } else {
            $this->catch_all_middlewares_append = array_merge($this->catch_all_middlewares_append, $middlewares);
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

        if (isset($update->message)) {  // message
            $update->withAttribute('issued_user', $update->message->from);

            // check command
            foreach ($this->command_routes as $command_route) {
                /** @var Command $command */
                $command = $command_route[0];
                if ($this->matchCommand($command, $update)) {
                    /** @var StandardRoute $route */
                    $route = $command_route[1];
                    $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                    $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
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
                    $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                    $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
                    return $route->call($update, [$text, $matches]);
                }
            }

            // text message default
            foreach ($this->text_default_routes as $route) {
                /** @var StandardRoute $route */
                $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
                $rsp = $route->call($update, [$text, []]);
                if (!$rsp->isEmpty()) {
                    return $rsp;
                }
            }
        } else if (isset($update->callback_query)) {  // callback
            $update->withAttribute('route_type', 'callback')
                ->withAttribute('issued_user', $update->callback_query->from);

            foreach ($this->callback_routes as $callback_route) {
                if ($this->matchCallback($callback_route[0], $update->callback_query)) {
                    /** @var StandardRoute $route */
                    $route = $callback_route[1];
                    $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                    $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
                    return $route->call(
                        $update->withAttribute('routed_identifier', $callback_route[0]),
                        $this->parseCallbackDataParams($update->callback_query)
                    );
                }
            }
        } else if (isset($update->inline_query)) {  // inline
            $update->withAttribute('route_type', 'inline')
                ->withAttribute('issued_user', $update->inline_query->from);

            // inline regex
            foreach ($this->inline_routes as $inline_route) {
                if (preg_match($inline_route[0], $update->inline_query->query, $matches)) {
                    /** @var StandardRoute $route */
                    $route = $inline_route[1];
                    $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                    $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
                    return $route->call($update, [$update->inline_query->query, $matches]);
                }
            }

            // inline default
            foreach ($this->inline_default_routes as $route) {
                /** @var StandardRoute $route */
                $route->prependMiddleware(...$this->catch_all_middlewares_prepend, ...$this->global_middlewares_prepend);
                $route->appendMiddleware(...$this->catch_all_middlewares_append, ...$this->global_middlewares_append);
                $rsp = $route->call($update, [$update->inline_query->query, []]);
                if (!$rsp->isEmpty()) {
                    return $rsp;
                }
            }
        }

        // other update types here ...

        $catchAll = StandardRoute::dummy();
        $catchAll->prependMiddleware(...$this->catch_all_middlewares_prepend);
        $catchAll->appendMiddleware(...$this->catch_all_middlewares_append);
        return $catchAll->call($update, []);
    }

    protected function verifyMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $i => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new Exception("\$middlewares[$i] must be an instance of MiddlewareInterface");
            }
        }
    }

    protected function matchCommand(Command $command, Update $update): bool
    {
        if (!in_array($update?->message?->chat?->type ?? '', $command->getAllowedChatTypes())) {
            return false;
        }

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
        return $message->text ?? $message->caption ?? null;
    }

    #[Pure]
    protected function getCommandRegex(Command $command): string
    {
        $prefix = preg_quote($command->getPrefix() . $command->getCommand(), '/');

        $subcmds = [];
        foreach ($command->getSubcommands() as $subc) {
            $subcmds[] = preg_quote($subc, '/');
        }

        if (empty($subcmds)) {
            return "/^$prefix(@$this->bot_username|)($|\s)/i";
        } else {
            $delimiter_raw = $command->getDelimiter();
            $delimiter = preg_quote($delimiter_raw, '/');
            if (mb_strlen($delimiter_raw) === 1) {
                $delimiter_regex = $delimiter;
            } else {
                $delimiter_regex = "[$delimiter]";
            }
            $subcmd = implode("$delimiter_regex+", $subcmds);

            return "/^$prefix(@$this->bot_username|)\s+$subcmd($|$delimiter_regex?)/i";
        }
    }

    protected function parseCommandParams(Command $command, Update $update): array
    {
        $msgtext = $this->getMessageText($update->message);
        if (!$msgtext) {
            return [];
        }

        $delimiter_raw = $command->getDelimiter();

        $param_string = trim(preg_replace($this->getCommandRegex($command), '', $msgtext), " \t\n\r\0\x0B$delimiter_raw");
        if (empty($param_string)) {
            return [];
        }

        $delimiter = preg_quote($delimiter_raw, '/');
        if (mb_strlen($delimiter_raw) === 1) {
            $regex = "/$delimiter+/";
        } else {
            $regex = "/[$delimiter]+/";
        }

        return preg_split($regex, $param_string);
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
