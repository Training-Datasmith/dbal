<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\OCI8\Exception\Unknown_Parameter_Index;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use function is_int;
use const OCI_B_BIN;
use const OCI_B_BLOB;
use function oci_bind_by_name;
use const OCI_COMMIT_ON_SUCCESS;
use const OCI_D_LOB;
use function oci_execute;
use function oci_new_descriptor;
use const OCI_NO_AUTO_COMMIT;
use const OCI_TEMP_BLOB;
use const SQLT_CHR;
final readonly class Statement implements Statement_Interface
{
    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource          $connection
     * @param resource          $statement
     * @param array<int,string> $parameterMap
     */
    public function __construct(private mixed $connection, private mixed $statement, private array $parameter_map, private Execution_Mode $execution_mode)
    {
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        if (is_int($param)) {
            if (!isset($this->parameter_map[$param])) {
                throw Unknown_Parameter_Index::new($param);
            }
            $param = $this->parameter_map[$param];
        }
        if ($type === Parameter_Type::LARGE_OBJECT) {
            if ($value !== null) {
                $lob = oci_new_descriptor($this->connection, OCI_D_LOB);
                $lob->write_temporary($value, OCI_TEMP_BLOB);
                $value =& $lob;
            } else {
                $type = Parameter_Type::STRING;
            }
        }
        if (!@oci_bind_by_name($this->statement, $param, $value, -1, $this->convert_parameter_type($type))) {
            throw Error::new($this->statement);
        }
    }
    /**
     * Converts DBAL parameter type to oci8 parameter type
     */
    private function convert_parameter_type(Parameter_Type $type): int
    {
        return match ($type) {
            Parameter_Type::BINARY => OCI_B_BIN,
            Parameter_Type::LARGE_OBJECT => OCI_B_BLOB,
            default => SQLT_CHR,
        };
    }
    public function execute(): Result
    {
        if ($this->execution_mode->is_auto_commit_enabled()) {
            $mode = OCI_COMMIT_ON_SUCCESS;
        } else {
            $mode = OCI_NO_AUTO_COMMIT;
        }
        $ret = @oci_execute($this->statement, $mode);
        if (!$ret) {
            throw Error::new($this->statement);
        }
        return new Result($this->statement);
    }
}