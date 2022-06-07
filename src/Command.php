<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use Exception;
use JetBrains\PhpStorm\Pure;
use Stringable;

class Command implements Stringable
{
    protected $command;

    /**
     * Command constructor.
     * @param string $command
     * @param array $allowed_chat_types
     * @param string $prefix
     * @param array $subcommands
     * @param string $delimiter only for subcommand, regex will be [$delimiter]+ (multiple characters) or $delimiter+ (single character)
     * @throws \Exception
     */
    public function __construct(
        string           $command,
        protected array  $allowed_chat_types = [],
        protected string $prefix = '/',
        protected array  $subcommands = [],
        protected string $delimiter = ' ',
    )
    {
        $command = ltrim($command, '/');
        if (!preg_match('/\w/', $command)) {
            throw new Exception('Invalid command');
        }

        $this->command = $command;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getSubcommands(): array
    {
        return $this->subcommands;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getAllowedChatTypes(): array
    {
        if (empty($this->allowed_chat_types)) {
            return ['private', 'group', 'supergroup', /*'channel'*/];
        }

        return $this->allowed_chat_types;
    }

    #[Pure]
    public function toString(): string
    {
        return rtrim($this->prefix . $this->command . ' ' . implode(' ', $this->subcommands));
    }

    #[Pure]
    public function __toString(): string
    {
        return $this->toString();
    }
}