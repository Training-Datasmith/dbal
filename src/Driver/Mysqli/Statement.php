<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli;

use function array_fill;
use function assert;
use function count;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\Failed_Reading_Stream_Offset;
use Doctrine\DBAL\Driver\Mysqli\Exception\Non_Stream_Resource_Used_As_Large_Object;
use Doctrine\DBAL\Driver\Mysqli\Exception\Statement_Error;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use function feof;
use function fread;
use function get_resource_type;
use function is_int;
use function is_resource;
use mysqli_sql_exception;
use mysqli_stmt;
use function str_repeat;
final class Statement implements Statement_Interface
{
    private const PARAMETER_TYPE_STRING = 's';
    private const PARAMETER_TYPE_INTEGER = 'i';
    private const PARAMETER_TYPE_BINARY = 'b';
    /** @var mixed[] */
    private array $bound_values;
    private string $types;
    /**
     * Contains ref values for bindValue().
     *
     * @var mixed[]
     */
    private array $values = [];
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private readonly mysqli_stmt $stmt)
    {
        $param_count = $this->stmt->param_count;
        $this->types = str_repeat(self::PARAMETER_TYPE_STRING, $param_count);
        $this->bound_values = array_fill(1, $param_count, null);
    }
    public function __destruct()
    {
        @$this->stmt->close();
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        assert(is_int($param));
        $this->types[$param - 1] = $this->convert_parameter_type($type);
        $this->values[$param] = $value;
        $this->bound_values[$param] =& $this->values[$param];
    }
    public function execute(): Result
    {
        if (count($this->bound_values) > 0) {
            $this->bind_parameters();
        }
        try {
            if (!$this->stmt->execute()) {
                throw Statement_Error::new($this->stmt);
            }
        } catch (mysqli_sql_exception $e) {
            throw Statement_Error::upcast($e);
        }
        return new Result($this->stmt, $this);
    }
    /**
     * Binds parameters with known types previously bound to the statement
     *
     * @throws Exception
     */
    private function bind_parameters(): void
    {
        $streams = $values = [];
        $types = $this->types;
        foreach ($this->bound_values as $parameter => $value) {
            assert(is_int($parameter));
            if (!isset($types[$parameter - 1])) {
                $types[$parameter - 1] = self::PARAMETER_TYPE_STRING;
            }
            if ($types[$parameter - 1] === self::PARAMETER_TYPE_BINARY) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw Non_Stream_Resource_Used_As_Large_Object::new($parameter);
                    }
                    $streams[$parameter] = $value;
                    $values[$parameter] = null;
                    continue;
                }
                $types[$parameter - 1] = self::PARAMETER_TYPE_STRING;
            }
            $values[$parameter] = $value;
        }
        if (!$this->stmt->bind_param($types, ...$values)) {
            throw Statement_Error::new($this->stmt);
        }
        $this->send_long_data($streams);
    }
    /**
     * Handle $this->_longData after regular query parameters have been bound
     *
     * @param array<int, resource> $streams
     *
     * @throws Exception
     */
    private function send_long_data(array $streams): void
    {
        foreach ($streams as $param_nr => $stream) {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw Failed_Reading_Stream_Offset::new($param_nr);
                }
                if (!$this->stmt->send_long_data($param_nr - 1, $chunk)) {
                    throw Statement_Error::new($this->stmt);
                }
            }
        }
    }
    private function convert_parameter_type(Parameter_Type $type): string
    {
        return match ($type) {
            Parameter_Type::NULL, Parameter_Type::STRING, Parameter_Type::ASCII, Parameter_Type::BINARY => self::PARAMETER_TYPE_STRING,
            Parameter_Type::INTEGER, Parameter_Type::BOOLEAN => self::PARAMETER_TYPE_INTEGER,
            Parameter_Type::LARGE_OBJECT => self::PARAMETER_TYPE_BINARY,
        };
    }
}