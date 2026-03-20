<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO\Sql_Srv;

use Doctrine\DBAL\Driver\Middleware\Abstract_Connection_Middleware;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use PDO;
final class Connection extends Abstract_Connection_Middleware
{
    public function __construct(private readonly Pdo_Connection $connection)
    {
        parent::__construct($connection);
    }
    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection->prepare($sql));
    }
    public function get_native_connection(): PDO
    {
        return $this->connection->get_native_connection();
    }
}