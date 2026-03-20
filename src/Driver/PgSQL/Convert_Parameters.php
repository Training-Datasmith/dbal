<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use function count;
use Doctrine\DBAL\SQL\Parser\Visitor;
use function implode;
final class Convert_Parameters implements Visitor
{
    /** @var list<string> */
    private array $buffer = [];
    /** @var array<array-key, int> */
    private array $parameter_map = [];
    public function accept_positional_parameter(string $sql): void
    {
        $position = count($this->parameter_map) + 1;
        $this->parameter_map[$position] = $position;
        $this->buffer[] = '$' . $position;
    }
    public function accept_named_parameter(string $sql): void
    {
        $position = count($this->parameter_map) + 1;
        $this->parameter_map[$sql] = $position;
        $this->buffer[] = '$' . $position;
    }
    public function accept_other(string $sql): void
    {
        $this->buffer[] = $sql;
    }
    public function get_sql(): string
    {
        return implode('', $this->buffer);
    }
    /** @return array<array-key, int> */
    public function get_parameter_map(): array
    {
        return $this->parameter_map;
    }
}