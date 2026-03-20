<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Parameter_Type;
abstract class Abstract_Statement_Middleware implements Statement
{
    public function __construct(private readonly Statement $wrapped_statement)
    {
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        $this->wrapped_statement->bind_value($param, $value, $type);
    }
    public function execute(): Result
    {
        return $this->wrapped_statement->execute();
    }
}