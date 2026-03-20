<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli;

use function array_column;
use function array_combine;
use function array_fill;
use function count;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\Mysqli\Exception\Statement_Error;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use mysqli_sql_exception;
use mysqli_stmt;
final class Result implements Result_Interface
{
    /**
     * Whether the statement result has columns. The property should be used only after the result metadata
     * has been fetched ({@see $metadataFetched}). Otherwise, the property value is undetermined.
     */
    private readonly bool $has_columns;
    /**
     * Mapping of statement result column indexes to their names. The property should be used only
     * if the statement result has columns ({@see $hasColumns}). Otherwise, the property value is undetermined.
     *
     * @var array<int,string>
     */
    private readonly array $column_names;
    /** @var mixed[] */
    private array $bound_values = [];
    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     *
     * @throws Exception
     */
    public function __construct(private readonly mysqli_stmt $statement)
    {
        $meta = $statement->result_metadata();
        $this->has_columns = $meta !== false;
        $this->column_names = $meta !== false ? array_column($meta->fetch_fields(), 'name') : [];
        if ($meta === false) {
            return;
        }
        $meta->free();
        // Store result of every execution which has it. Otherwise it will be impossible
        // to execute a new statement in case if the previous one has non-fetched rows
        // @link http://dev.mysql.com/doc/refman/5.7/en/commands-out-of-sync.html
        $this->statement->store_result();
        // Bind row values _after_ storing the result. Otherwise, if mysqli is compiled with libmysql,
        // it will have to allocate as much memory as it may be needed for the given column type
        // (e.g. for a LONGBLOB column it's 4 gigabytes)
        // @link https://bugs.php.net/bug.php?id=51386#1270673122
        //
        // Make sure that the values are bound after each execution. Otherwise, if free() has been
        // previously called on the result, the values are unbound making the statement unusable.
        //
        // It's also important that row values are bound after _each_ call to store_result(). Otherwise,
        // if mysqli is compiled with libmysql, subsequently fetched string values will get truncated
        // to the length of the ones fetched during the previous execution.
        $this->bound_values = array_fill(0, count($this->column_names), null);
        // The following is necessary as PHP cannot handle references to properties properly
        $refs =& $this->bound_values;
        if (!$this->statement->bind_result(...$refs)) {
            throw Statement_Error::new($this->statement);
        }
    }
    public function fetch_numeric(): array|false
    {
        try {
            $ret = $this->statement->fetch();
        } catch (mysqli_sql_exception $e) {
            throw Statement_Error::upcast($e);
        }
        if ($ret === false) {
            throw Statement_Error::new($this->statement);
        }
        if ($ret === null) {
            return false;
        }
        $values = [];
        foreach ($this->bound_values as $v) {
            $values[] = $v;
        }
        return $values;
    }
    public function fetch_associative(): array|false
    {
        $values = $this->fetch_numeric();
        if ($values === false) {
            return false;
        }
        return array_combine($this->column_names, $values);
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
    public function row_count(): int|string
    {
        if ($this->has_columns) {
            return $this->statement->num_rows;
        }
        return $this->statement->affected_rows;
    }
    public function column_count(): int
    {
        return $this->statement->field_count;
    }
    public function get_column_name(int $index): string
    {
        return $this->column_names[$index] ?? throw Invalid_Column_Index::new($index);
    }
    public function free(): void
    {
        $this->statement->free_result();
    }
}