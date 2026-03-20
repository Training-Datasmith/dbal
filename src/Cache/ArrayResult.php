<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Cache;

use function array_combine;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
/** @internal The class is internal to the caching layer implementation. */
final class Array_Result implements Result
{
    private int $num = 0;
    /**
     * @param list<string>      $columnNames The names of the result columns. Must be non-empty.
     * @param list<list<mixed>> $rows        The rows of the result. Each row must have the same number of columns
     *                                       as the number of column names.
     */
    public function __construct(private readonly array $column_names, private array $rows)
    {
    }
    public function fetch_numeric(): array|false
    {
        return $this->fetch();
    }
    public function fetch_associative(): array|false
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        return array_combine($this->column_names, $row);
    }
    public function fetch_one(): mixed
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        return $row[0];
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_numeric(): array
    {
        return Fetch_Utils::fetch_all_numeric($this);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_associative(): array
    {
        return Fetch_Utils::fetch_all_associative($this);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_first_column(): array
    {
        return Fetch_Utils::fetch_first_column($this);
    }
    public function row_count(): int
    {
        return count($this->rows);
    }
    public function column_count(): int
    {
        return count($this->column_names);
    }
    public function get_column_name(int $index): string
    {
        return $this->column_names[$index] ?? throw Invalid_Column_Index::new($index);
    }
    public function free(): void
    {
        $this->rows = [];
    }
    /** @return array{list<string>, list<list<mixed>>} */
    public function __serialize(): array
    {
        return [$this->column_names, $this->rows];
    }
    /** @param mixed[] $data */
    public function __unserialize(array $data): void
    {
        // Handle objects serialized with DBAL 4.1 and earlier.
        if (isset($data["\x00" . self::class . "\x00data"])) {
            /** @var list<array<string, mixed>> $legacyData */
            $legacy_data = $data["\x00" . self::class . "\x00data"];
            $this->column_names = array_keys($legacy_data[0] ?? []);
            $this->rows = array_map(array_values(...), $legacy_data);
            return;
        }
        [$this->column_names, $this->rows] = $data;
    }
    /** @return list<mixed>|false */
    private function fetch(): array|false
    {
        if (!isset($this->rows[$this->num])) {
            return false;
        }
        return $this->rows[$this->num++];
    }
}