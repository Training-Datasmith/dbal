<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2;

use function assert;
use const DB2_BINARY;
use function db2_bind_param;
use const DB2_CHAR;
use function db2_execute;
use const DB2_LONG;
use const DB2_PARAM_FILE;
use const DB2_PARAM_IN;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Cannot_Copy_Stream_To_Stream;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Cannot_Create_Temporary_File;
use Doctrine\DBAL\Driver\IBMDB2\Exception\Statement_Error;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use function error_get_last;
use function fclose;
use function is_int;
use function is_resource;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function tmpfile;
final class Statement implements Statement_Interface
{
    /** @var mixed[] */
    private array $parameters = [];
    /**
     * Map of LOB parameter positions to the tuples containing reference to the variable bound to the driver statement
     * and the temporary file handle bound to the underlying statement
     *
     * @var array<int,string|resource|null>
     */
    private array $lobs = [];
    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource $stmt
     */
    public function __construct(private readonly mixed $stmt)
    {
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        assert(is_int($param));
        switch ($type) {
            case Parameter_Type::INTEGER:
                $this->bind($param, $value, DB2_PARAM_IN, DB2_LONG);
                break;
            case Parameter_Type::LARGE_OBJECT:
                $this->lobs[$param] =& $value;
                break;
            default:
                $this->bind($param, $value, DB2_PARAM_IN, DB2_CHAR);
                break;
        }
    }
    /** @throws Exception */
    private function bind(int $position, mixed &$variable, int $parameter_type, int $data_type): void
    {
        $this->parameters[$position] =& $variable;
        if (!db2_bind_param($this->stmt, $position, '', $parameter_type, $data_type)) {
            throw Statement_Error::new($this->stmt);
        }
    }
    public function execute(): Result
    {
        $handles = $this->bind_lobs();
        $result = @db2_execute($this->stmt, $this->parameters);
        foreach ($handles as $handle) {
            fclose($handle);
        }
        $this->lobs = [];
        if ($result === false) {
            throw Statement_Error::new($this->stmt);
        }
        return new Result($this->stmt);
    }
    /**
     * @return list<resource>
     *
     * @throws Exception
     */
    private function bind_lobs(): array
    {
        $handles = [];
        foreach ($this->lobs as $param => $value) {
            if (is_resource($value)) {
                $handle = $handles[] = $this->create_temporary_file();
                $path = stream_get_meta_data($handle)['uri'] ?? null;
                assert($path !== null);
                $this->copy_stream_to_stream($value, $handle);
                $this->bind($param, $path, DB2_PARAM_FILE, DB2_BINARY);
            } else {
                $this->bind($param, $value, DB2_PARAM_IN, DB2_CHAR);
            }
            unset($value);
        }
        return $handles;
    }
    /**
     * @return resource
     *
     * @throws Exception
     */
    private function create_temporary_file()
    {
        $handle = @tmpfile();
        if ($handle === false) {
            throw Cannot_Create_Temporary_File::new(error_get_last());
        }
        return $handle;
    }
    /**
     * @param resource $source
     * @param resource $target
     *
     * @throws Exception
     */
    private function copy_stream_to_stream($source, $target): void
    {
        if (@stream_copy_to_stream($source, $target) === false) {
            throw Cannot_Copy_Stream_To_Stream::new(error_get_last());
        }
    }
}