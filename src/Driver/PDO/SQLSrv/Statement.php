<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Sql_Srv;

use Doctrine\DBAL\Driver\Middleware\Abstract_Statement_Middleware;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Parameter_Type;
use PDO;
final class Statement extends Abstract_Statement_Middleware
{
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private readonly PDOStatement $statement)
    {
        parent::__construct($statement);
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        switch ($type) {
            case Parameter_Type::LARGE_OBJECT:
            case Parameter_Type::BINARY:
                $this->statement->bind_param_with_driver_options($param, $value, $type, PDO::SQLSRV_ENCODING_BINARY);
                break;
            case Parameter_Type::ASCII:
                $this->statement->bind_param_with_driver_options($param, $value, Parameter_Type::STRING, PDO::SQLSRV_ENCODING_SYSTEM);
                break;
            default:
                $this->statement->bind_value($param, $value, $type);
        }
    }
}