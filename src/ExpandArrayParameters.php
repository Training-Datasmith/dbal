<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

use function array_fill;
use function array_key_exists;
use function count;
use Doctrine\DBAL\Array_Parameters\Exception\Missing_Named_Parameter;
use Doctrine\DBAL\Array_Parameters\Exception\Missing_Positional_Parameter;
use Doctrine\DBAL\SQL\Parser\Visitor;
use Doctrine\DBAL\Types\Type;
use function implode;
use function substr;
/** @phpstan-import-type WrapperParameterTypeArray from Connection */
final class Expand_Array_Parameters implements Visitor
{
    private int $original_parameter_index = 0;
    /** @var list<string> */
    private array $converted_sql = [];
    /** @var list<mixed> */
    private array $converted_parameters = [];
    /** @var array<int<0, max>,string|ParameterType|Type> */
    private array $converted_types = [];
    /**
     * @param array<int, mixed>|array<string, mixed> $parameters
     * @phpstan-param WrapperParameterTypeArray $types
     */
    public function __construct(private readonly array $parameters, private readonly array $types)
    {
    }
    public function accept_positional_parameter(string $sql): void
    {
        $index = $this->original_parameter_index;
        if (!array_key_exists($index, $this->parameters)) {
            throw Missing_Positional_Parameter::new($index);
        }
        $this->accept_parameter($index, $this->parameters[$index]);
        $this->original_parameter_index++;
    }
    public function accept_named_parameter(string $sql): void
    {
        $name = substr($sql, 1);
        if (!array_key_exists($name, $this->parameters)) {
            throw Missing_Named_Parameter::new($name);
        }
        $this->accept_parameter($name, $this->parameters[$name]);
    }
    public function accept_other(string $sql): void
    {
        $this->converted_sql[] = $sql;
    }
    public function get_sql(): string
    {
        return implode('', $this->converted_sql);
    }
    /** @return list<mixed> */
    public function get_parameters(): array
    {
        return $this->converted_parameters;
    }
    private function accept_parameter(int|string $key, mixed $value): void
    {
        if (!isset($this->types[$key])) {
            $this->converted_sql[] = '?';
            $this->converted_parameters[] = $value;
            return;
        }
        $type = $this->types[$key];
        if (!$type instanceof Array_Parameter_Type) {
            $this->append_typed_parameter([$value], $type);
            return;
        }
        if (count($value) === 0) {
            $this->converted_sql[] = 'NULL';
            return;
        }
        $this->append_typed_parameter($value, Array_Parameter_Type::to_element_parameter_type($type));
    }
    /** @return array<int<0, max>,string|ParameterType|Type> */
    public function get_types(): array
    {
        return $this->converted_types;
    }
    /** @param list<mixed> $values */
    private function append_typed_parameter(array $values, string|Parameter_Type|Type $type): void
    {
        $this->converted_sql[] = implode(', ', array_fill(0, count($values), '?'));
        $index = count($this->converted_parameters);
        foreach ($values as $value) {
            $this->converted_parameters[] = $value;
            $this->converted_types[$index] = $type;
            $index++;
        }
    }
}