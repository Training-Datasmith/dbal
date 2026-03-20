<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sq_Lite3;

use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use const SQLITE3_ASSOC;
use const SQLITE3_NUM;
use Sq_Lite3result;
final class Result implements Result_Interface
{
    /** @internal The result can be only instantiated by its driver connection or statement. */
    public function __construct(private ?Sq_Lite3result $result, private readonly int $changes)
    {
    }
    public function fetch_numeric(): array|false
    {
        if ($this->result === null) {
            return false;
        }
        return $this->result->fetch_array(SQLITE3_NUM);
    }
    public function fetch_associative(): array|false
    {
        if ($this->result === null) {
            return false;
        }
        return $this->result->fetch_array(SQLITE3_ASSOC);
    }
    public function fetch_one(): mixed
    {
        return Fetch_Utils::fetch_one($this);
    }
    /** @inheritDoc */
    public function fetch_all_numeric(): array
    {
        return Fetch_Utils::fetch_all_numeric($this);
    }
    /** @inheritDoc */
    public function fetch_all_associative(): array
    {
        return Fetch_Utils::fetch_all_associative($this);
    }
    /** @inheritDoc */
    public function fetch_first_column(): array
    {
        return Fetch_Utils::fetch_first_column($this);
    }
    public function row_count(): int
    {
        return $this->changes;
    }
    public function column_count(): int
    {
        if ($this->result === null) {
            return 0;
        }
        return $this->result->num_columns();
    }
    public function get_column_name(int $index): string
    {
        if ($this->result === null) {
            throw Invalid_Column_Index::new($index);
        }
        $name = $this->result->column_name($index);
        if ($name === false) {
            throw Invalid_Column_Index::new($index);
        }
        return $name;
    }
    public function free(): void
    {
        if ($this->result === null) {
            return;
        }
        $this->result->finalize();
        $this->result = null;
    }
}