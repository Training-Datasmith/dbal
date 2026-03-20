<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

use function count;
use Doctrine\DBAL\SQL\Parser\Visitor;
use function implode;
/**
 * Converts positional (?) into named placeholders (:param<num>).
 *
 * Oracle does not support positional parameters, hence this method converts all
 * positional parameters into artificially named parameters.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class Convert_Positional_To_Named_Placeholders implements Visitor
{
    /** @var list<string> */
    private array $buffer = [];
    /** @var array<int,string> */
    private array $parameter_map = [];
    public function accept_other(string $sql): void
    {
        $this->buffer[] = $sql;
    }
    public function accept_positional_parameter(string $sql): void
    {
        $position = count($this->parameter_map) + 1;
        $param = ':param' . $position;
        $this->parameter_map[$position] = $param;
        $this->buffer[] = $param;
    }
    public function accept_named_parameter(string $sql): void
    {
        $this->buffer[] = $sql;
    }
    public function get_sql(): string
    {
        return implode('', $this->buffer);
    }
    /** @return array<int,string> */
    public function get_parameter_map(): array
    {
        return $this->parameter_map;
    }
}