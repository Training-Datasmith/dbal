<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Middleware\Abstract_Statement_Middleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use Psr\Log\Logger_Interface;
final class Statement extends Abstract_Statement_Middleware
{
    /** @var array<int,mixed>|array<string,mixed> */
    private array $params = [];
    /** @var array<int,ParameterType>|array<string,ParameterType> */
    private array $types = [];
    /** @internal This statement can be only instantiated by its connection. */
    public function __construct(Statement_Interface $statement, private readonly Logger_Interface $logger, private readonly string $sql)
    {
        parent::__construct($statement);
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        $this->params[$param] = $value;
        $this->types[$param] = $type;
        parent::bind_value($param, $value, $type);
    }
    public function execute(): Result_Interface
    {
        $this->logger->debug('Executing statement: {sql} (parameters: {params}, types: {types})', ['sql' => $this->sql, 'params' => $this->params, 'types' => $this->types]);
        return parent::execute();
    }
}