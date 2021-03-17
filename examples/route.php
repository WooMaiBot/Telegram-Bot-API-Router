<?php

use Psr\Http\Message\RequestInterface;
use WooMaiLabs\TelegramBotAPI\Models\Update;
use WooMaiLabs\TelegramBotAPI\Router\Callback;
use WooMaiLabs\TelegramBotAPI\Router\Command;
use WooMaiLabs\TelegramBotAPI\Router\MiddlewareInterface;
use WooMaiLabs\TelegramBotAPI\Router\Router;
use WooMaiLabs\TelegramBotAPI\Router\WebhookResponse;

require '../vendor/autoload.php';

$handler1 = function (Update $update) {
    return new WebhookResponse('sendMessage', [
        'chat_id' => '@example_username',
        'text' => 'Hello World!'
    ]);
};

$handler2 = function (Update $update) {
    // you can also return void
};

$router = new Router(1145141919, 'ExampleBot');

/**
 * Standard command
 * @example /help
 */
$router->command(new Command('help'), $handler1);

/**
 * Custom command prefix
 * @example !ban
 */
$router->command(new Command('ban', '!'), $handler2);

/**
 * Subcommand support
 * @example /test_command subcommand sub_subcommand [param 1] [param 2] ...
 */
$router->command(new Command('test_command', subcommands: ['subcommand', 'sub_subcommand']), $handler2);

/**
 * work with middlewares (FIFO, middleware 1 -> middleware 2)
 * @var MiddlewareInterface $middleware1
 * @var MiddlewareInterface $middleware2
 */
$router->command(new Command('hello'), $handler1, $middleware1, $middleware2);

/**
 * Command alias
 */
$router->command([new Command('hello'), new Command('hi')], $handler2, $middleware1);

/**
 * Callback Query
 */
$router->callback(new Callback('action1'), $handler1, $middleware2);

/**
 * Plain text with regex
 */
$router->text('/^https?:\/\/example\.com/i', $handler1, $middleware1);

/**
 * Plain text default route
 */
$router->text('', $handler1, $middleware1);

/**
 * Inline Query
 * It's similar to Plain text
 */
$router->inline('/^help/', $handler1, $middleware1);
$router->inline('', $handler2, $middleware1, $middleware2);


/**
 * Global middlewares.
 * Runs before any other middleware and any type of update.
 */
$router->addGlobalMiddlewares($middleware1, $middleware2);

/**
 * Route the webhook
 * @var RequestInterface $request
 */
$router->route(request: $request);

/**
 * You can also use raw update object to route
 */
//$raw_update = json_decode('php://input');
//$router->route(update_object: $raw_update);
