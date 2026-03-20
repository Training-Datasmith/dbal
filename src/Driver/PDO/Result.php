<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use PDO;
use PDOException;
use PDOStatement;
use Value_Error;
final readonly class Result implements Result_Interface
{
    /** @internal The result can be only instantiated by its driver connection or statement. */
    public function __construct(private PDOStatement $statement)
    {
    }
    public function fetch_numeric(): array|false
    {
        return $this->fetch(PDO::FETCH_NUM);
    }
    public function fetch_associative(): array|false
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }
    public function fetch_one(): mixed
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_numeric(): array
    {
        return $this->fetch_all(PDO::FETCH_NUM);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_associative(): array
    {
        return $this->fetch_all(PDO::FETCH_ASSOC);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_first_column(): array
    {
        return $this->fetch_all(PDO::FETCH_COLUMN);
    }
    public function row_count(): int
    {
        try {
            return $this->statement->row_count();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function column_count(): int
    {
        try {
            return $this->statement->column_count();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    /** @throws Exception */
    public function get_column_name(int $index): string
    {
        try {
            $meta = $this->statement->get_column_meta($index);
        } catch (Value_Error $exception) {
            throw Invalid_Column_Index::new($index, $exception);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        if ($meta === false) {
            throw Invalid_Column_Index::new($index);
        }
        return $meta['name'];
    }
    public function free(): void
    {
        $this->statement->close_cursor();
    }
    /**
     * @phpstan-param PDO::FETCH_* $mode
     *
     * @throws Exception
     */
    private function fetch(int $mode): mixed
    {
        try {
            return $this->statement->fetch($mode);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    /**
     * @phpstan-param PDO::FETCH_* $mode
     *
     * @return list<mixed>
     *
     * @throws Exception
     */
    private function fetch_all(int $mode): array
    {
        try {
            return $this->statement->fetch_all($mode);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
}