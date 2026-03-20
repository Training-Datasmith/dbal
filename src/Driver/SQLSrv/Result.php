<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sql_Srv;

use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use function sqlsrv_fetch;
use function sqlsrv_fetch_array;
use const SQLSRV_FETCH_ASSOC;
use const SQLSRV_FETCH_NUMERIC;
use function sqlsrv_field_metadata;
use function sqlsrv_num_fields;
use function sqlsrv_rows_affected;
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
        return $this->fetch(SQLSRV_FETCH_NUMERIC);
    }
    public function fetch_associative(): array|false
    {
        return $this->fetch(SQLSRV_FETCH_ASSOC);
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
        $count = sqlsrv_rows_affected($this->statement);
        if ($count !== false) {
            return $count;
        }
        return 0;
    }
    public function column_count(): int
    {
        $count = sqlsrv_num_fields($this->statement);
        if ($count !== false) {
            return $count;
        }
        return 0;
    }
    public function get_column_name(int $index): string
    {
        $meta = sqlsrv_field_metadata($this->statement);
        if ($meta === false || !isset($meta[$index])) {
            throw Invalid_Column_Index::new($index);
        }
        return $meta[$index]['Name'];
    }
    public function free(): void
    {
        // emulate it by fetching and discarding rows, similarly to what PDO does in this case
        // @link http://php.net/manual/en/pdostatement.closecursor.php
        // @link https://github.com/php/php-src/blob/php-7.0.11/ext/pdo/pdo_stmt.c#L2075
        // deliberately do not consider multiple result sets, since doctrine/dbal doesn't support them
        while (sqlsrv_fetch($this->statement) === true) {
        }
    }
    private function fetch(int $fetch_type): mixed
    {
        return sqlsrv_fetch_array($this->statement, $fetch_type) ?? false;
    }
}