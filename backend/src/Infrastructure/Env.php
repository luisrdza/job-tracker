<?php
declare(strict_types=1);
namespace App\Infrastructure;
class Env
{
    public function __construct(
        private array $variables = [],
        private array $requiredKeys = [
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD'
        ]
    ) {
        $this->variables = empty($variables) ? $_ENV : $variables;
        $this->validate();
    }

    public function validate(): void
    {
        foreach ($this->requiredKeys as $key => $value) {
            if (!array_key_exists($value, $this->variables) || trim((string) $this->variables[$value]) === '') {
                throw new \RuntimeException($value . " is required.");
            }
        }

        $port = $this->variables['DB_PORT'];
        if (filter_var($port, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]]) === false) {
            throw new \RuntimeException('DB_PORT must be a numeric value.');
        }
    }

    public function get(string $key): string
    {
        if (!isset($this->variables[$key])) {
            throw new \RuntimeException("Variable {$key} no definida.");
        }

        return (string) $this->variables[$key];
    }

    public function getInt(string $key): int
    {
        return (int) $this->get($key);
    }
}