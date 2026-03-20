<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use PDO;
use PDOException;
use PDOStatement;
final readonly class Statement implements Statement_Interface
{
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private PDOStatement $stmt)
    {
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        $pdo_type = $this->convert_param_type($type);
        try {
            $this->stmt->bind_value($param, $value, $pdo_type);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    /**
     * @internal Driver options can be only specified by a PDO-based driver.
     *
     * @throws ExceptionInterface
     */
    public function bind_param_with_driver_options(string|int $param, mixed &$variable, Parameter_Type $type, mixed $driver_options): void
    {
        $pdo_type = $this->convert_param_type($type);
        try {
            $this->stmt->bind_param($param, $variable, $pdo_type, 0, $driver_options);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
    public function execute(): Result
    {
        try {
            $this->stmt->execute();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        return new Result($this->stmt);
    }
    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @phpstan-return PDO::PARAM_*
     */
    private function convert_param_type(Parameter_Type $type): int
    {
        return match ($type) {
            Parameter_Type::NULL => PDO::PARAM_NULL,
            Parameter_Type::INTEGER => PDO::PARAM_INT,
            Parameter_Type::STRING, Parameter_Type::ASCII => PDO::PARAM_STR,
            Parameter_Type::BINARY, Parameter_Type::LARGE_OBJECT => PDO::PARAM_LOB,
            Parameter_Type::BOOLEAN => PDO::PARAM_BOOL,
        };
    }
}