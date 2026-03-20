<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2;

use function db2_fetch_array;
use function db2_fetch_assoc;
use function db2_field_name;
use function db2_free_result;
use function db2_num_fields;
use function db2_num_rows;
use function db2_stmt_error;
use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Statement_Error;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
final readonly class Result implements Result_Interface
{
    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @param resource $statement
     */
    public function __construct(private mixed $statement)
    {
    }
    public function fetch_numeric(): array|false
    {
        $row = @db2_fetch_array($this->statement);
        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw Statement_Error::new($this->statement);
        }
        return $row;
    }
    public function fetch_associative(): array|false
    {
        $row = @db2_fetch_assoc($this->statement);
        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw Statement_Error::new($this->statement);
        }
        return $row;
    }
    public function fetch_one(): mixed
    {
        return Fetch_Utils::fetch_one($this);
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
        $num_rows = @db2_num_rows($this->statement);
        if ($num_rows === false) {
            throw Statement_Error::new($this->statement);
        }
        return $num_rows;
    }
    public function column_count(): int
    {
        $count = db2_num_fields($this->statement);
        if ($count !== false) {
            return $count;
        }
        return 0;
    }
    public function get_column_name(int $index): string
    {
        $name = db2_field_name($this->statement, $index);
        if ($name === false) {
            throw Invalid_Column_Index::new($index);
        }
        return $name;
    }
    public function free(): void
    {
        db2_free_result($this->statement);
    }
}