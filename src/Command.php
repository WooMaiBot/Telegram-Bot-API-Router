<?php


namespace WooMaiLabs\TelegramBotAPI\Router;


use Exception;
use JetBrains\PhpStorm\Pure;

class Command implements \Stringable
{
    protected $command;

    /**
     * Command constructor.
     * @param string $command
     * @param string $prefix
     * @param array $subcommands
     * @throws Exception
     */
    public function __construct(string $command, protected string $prefix = '/', protected array $subcommands = [])
    {
        $command = ltrim($command, '/');
        if (!preg_match('/[a-z0-9_]/i', $command)) {
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

    #[Pure] public function toString(): string
    {
        return rtrim($this->prefix . $this->command . ' ' . implode(' ', $this->subcommands));
    }

    #[Pure] public function __toString(): string
    {
        return $this->toString();
    }
}