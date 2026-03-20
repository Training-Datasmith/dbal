<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use function get_debug_type;
use LogicException;
use function method_exists;
use function sprintf;
abstract class Abstract_Result_Middleware implements Result
{
    public function __construct(private readonly Result $wrapped_result)
    {
    }
    public function fetch_numeric(): array|false
    {
        return $this->wrapped_result->fetch_numeric();
    }
    public function fetch_associative(): array|false
    {
        return $this->wrapped_result->fetch_associative();
    }
    public function fetch_one(): mixed
    {
        return $this->wrapped_result->fetch_one();
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_numeric(): array
    {
        return $this->wrapped_result->fetch_all_numeric();
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_associative(): array
    {
        return $this->wrapped_result->fetch_all_associative();
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_first_column(): array
    {
        return $this->wrapped_result->fetch_first_column();
    }
    public function row_count(): int|string
    {
        return $this->wrapped_result->row_count();
    }
    public function column_count(): int
    {
        return $this->wrapped_result->column_count();
    }
    public function get_column_name(int $index): string
    {
        if (!method_exists($this->wrapped_result, 'getColumnName')) {
            throw new LogicException(sprintf('The driver result %s does not support accessing the column name.', get_debug_type($this->wrapped_result)));
        }
        return $this->wrapped_result->get_column_name($index);
    }
    public function free(): void
    {
        $this->wrapped_result->free();
    }
}