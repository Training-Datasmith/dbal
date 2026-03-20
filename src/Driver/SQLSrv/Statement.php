<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sql_Srv;

use function assert;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Sql_Srv\Exception\Error;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use function is_int;
use const SQLSRV_ENC_BINARY;
use const SQLSRV_ENC_CHAR;
use function sqlsrv_execute;
use const SQLSRV_PARAM_IN;
use function SQLSRV_PHPTYPE_STREAM;
use function SQLSRV_PHPTYPE_STRING;
use function sqlsrv_prepare;
use function SQLSRV_SQLTYPE_VARBINARY;
use function stripos;
final class Statement implements Statement_Interface
{
    /**
     * The SQLSRV statement resource.
     *
     * @var resource|null
     */
    private $stmt;
    /**
     * References to the variables bound as statement parameters.
     *
     * @var array<int, mixed>
     */
    private array $variables = [];
    /**
     * Bound parameter types.
     *
     * @var array<int, ParameterType>
     */
    private array $types = [];
    /**
     * Append to any INSERT query to retrieve the last insert id.
     */
    private const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';
    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource $conn
     */
    public function __construct(private readonly mixed $conn, private string $sql)
    {
        if (stripos($sql, 'INSERT INTO ') !== 0) {
            return;
        }
        $this->sql .= self::LAST_INSERT_ID_SQL;
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        assert(is_int($param));
        $this->variables[$param] = $value;
        $this->types[$param] = $type;
    }
    public function execute(): Result
    {
        $this->stmt ??= $this->prepare();
        if (!sqlsrv_execute($this->stmt)) {
            throw Error::new();
        }
        return new Result($this->stmt);
    }
    /**
     * Prepares SQL Server statement resource
     *
     * @return resource
     *
     * @throws Exception
     */
    private function prepare()
    {
        $params = [];
        foreach ($this->variables as $column => &$variable) {
            switch ($this->types[$column]) {
                case Parameter_Type::LARGE_OBJECT:
                    $params[$column - 1] = [&$variable, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max')];
                    break;
                case Parameter_Type::BINARY:
                    $params[$column - 1] = [&$variable, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY)];
                    break;
                case Parameter_Type::ASCII:
                    $params[$column - 1] = [&$variable, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR)];
                    break;
                default:
                    $params[$column - 1] =& $variable;
                    break;
            }
        }
        $stmt = sqlsrv_prepare($this->conn, $this->sql, $params);
        if ($stmt === false) {
            throw Error::new();
        }
        return $stmt;
    }
}