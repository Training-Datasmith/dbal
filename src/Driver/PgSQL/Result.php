<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use function array_keys;
use function array_map;
use function assert;
use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\Pg_Sql\Exception\Unexpected_Value;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use function hex2bin;
use function pg_affected_rows;
use function pg_fetch_all;
use function pg_fetch_all_columns;
use function pg_fetch_assoc;
use function pg_fetch_row;
use function pg_field_name;
use function pg_field_type;
use function pg_free_result;
use function pg_num_fields;
use Pg_Sql\Result as PgSqlResult;
use const PGSQL_ASSOC;
use const PGSQL_NUM;
use const PHP_INT_SIZE;
use function substr;
use Value_Error;
final class Result implements Result_Interface
{
    public function __construct(private ?Pg_Sql_Result $result)
    {
    }
    public function __destruct()
    {
        if (!isset($this->result)) {
            return;
        }
        $this->free();
    }
    /** {@inheritDoc} */
    public function fetch_numeric(): array|false
    {
        if ($this->result === null) {
            return false;
        }
        $row = pg_fetch_row($this->result);
        if ($row === false) {
            return false;
        }
        return $this->map_numeric_row($row, $this->fetch_numeric_column_types());
    }
    /** {@inheritDoc} */
    public function fetch_associative(): array|false
    {
        if ($this->result === null) {
            return false;
        }
        $row = pg_fetch_assoc($this->result);
        if ($row === false) {
            return false;
        }
        return $this->map_associative_row($row, $this->fetch_associative_column_types());
    }
    /** {@inheritDoc} */
    public function fetch_one(): mixed
    {
        return Fetch_Utils::fetch_one($this);
    }
    /** {@inheritDoc} */
    public function fetch_all_numeric(): array
    {
        if ($this->result === null) {
            return [];
        }
        $types = $this->fetch_numeric_column_types();
        return array_map(fn(array $row): array => $this->map_numeric_row($row, $types), pg_fetch_all($this->result, PGSQL_NUM));
    }
    /** {@inheritDoc} */
    public function fetch_all_associative(): array
    {
        if ($this->result === null) {
            return [];
        }
        $types = $this->fetch_associative_column_types();
        return array_map(fn(array $row): array => $this->map_associative_row($row, $types), pg_fetch_all($this->result, PGSQL_ASSOC));
    }
    /** {@inheritDoc} */
    public function fetch_first_column(): array
    {
        if ($this->result === null) {
            return [];
        }
        $postgres_type = pg_field_type($this->result, 0);
        return array_map(fn(?string $value): bool|float|int|string|null => $this->map_type($postgres_type, $value), pg_fetch_all_columns($this->result));
    }
    public function row_count(): int
    {
        if ($this->result === null) {
            return 0;
        }
        return pg_affected_rows($this->result);
    }
    public function column_count(): int
    {
        if ($this->result === null) {
            return 0;
        }
        return pg_num_fields($this->result);
    }
    public function get_column_name(int $index): string
    {
        if ($this->result === null) {
            throw Invalid_Column_Index::new($index);
        }
        try {
            return pg_field_name($this->result, $index);
        } catch (Value_Error) {
            throw Invalid_Column_Index::new($index);
        }
    }
    public function free(): void
    {
        if ($this->result === null) {
            return;
        }
        pg_free_result($this->result);
        $this->result = null;
    }
    /** @return array<int, string> */
    private function fetch_numeric_column_types(): array
    {
        assert($this->result !== null);
        $types = [];
        $num_fields = pg_num_fields($this->result);
        for ($i = 0; $i < $num_fields; ++$i) {
            $types[$i] = pg_field_type($this->result, $i);
        }
        return $types;
    }
    /** @return array<string, string> */
    private function fetch_associative_column_types(): array
    {
        assert($this->result !== null);
        $types = [];
        $num_fields = pg_num_fields($this->result);
        for ($i = 0; $i < $num_fields; ++$i) {
            $types[pg_field_name($this->result, $i)] = pg_field_type($this->result, $i);
        }
        return $types;
    }
    /**
     * @param list<string|null>  $row
     * @param array<int, string> $types
     *
     * @return list<mixed>
     */
    private function map_numeric_row(array $row, array $types): array
    {
        assert($this->result !== null);
        return array_map(fn(?string $value, $field): bool|float|int|string|null => $this->map_type($types[$field], $value), $row, array_keys($row));
    }
    /**
     * @param array<string, string|null> $row
     * @param array<string, string>      $types
     *
     * @return array<string, mixed>
     */
    private function map_associative_row(array $row, array $types): array
    {
        assert($this->result !== null);
        $mapped_row = [];
        foreach ($row as $field => $value) {
            $mapped_row[$field] = $this->map_type($types[$field], $value);
        }
        return $mapped_row;
    }
    private function map_type(string $postgres_type, ?string $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }
        return match ($postgres_type) {
            'bool' => match ($value) {
                't' => true,
                'f' => false,
                default => throw Unexpected_Value::new($value, $postgres_type),
            },
            'bytea' => hex2bin(substr($value, 2)),
            'float4', 'float8' => (float) $value,
            'int2', 'int4' => (int) $value,
            'int8' => PHP_INT_SIZE >= 8 ? (int) $value : $value,
            default => $value,
        };
    }
}