<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sq_Lite3;

use function assert;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use Sq_Lite3;
use const SQLITE3_BLOB;
use const SQLITE3_INTEGER;
use const SQLITE3_NULL;
use const SQLITE3_TEXT;
use Sq_Lite3stmt;
final readonly class Statement implements Statement_Interface
{
    private const TYPE_BLOB = SQLITE3_BLOB;
    private const TYPE_INTEGER = SQLITE3_INTEGER;
    private const TYPE_NULL = SQLITE3_NULL;
    private const TYPE_TEXT = SQLITE3_TEXT;
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private Sq_Lite3 $connection, private Sq_Lite3stmt $statement)
    {
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        $this->statement->bind_value($param, $value, $this->convert_param_type($type));
    }
    public function execute(): Result
    {
        try {
            $result = $this->statement->execute();
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        assert($result !== false);
        return new Result($result, $this->connection->changes());
    }
    /** @phpstan-return self::TYPE_* */
    private function convert_param_type(Parameter_Type $type): int
    {
        return match ($type) {
            Parameter_Type::NULL => self::TYPE_NULL,
            Parameter_Type::INTEGER, Parameter_Type::BOOLEAN => self::TYPE_INTEGER,
            Parameter_Type::STRING, Parameter_Type::ASCII => self::TYPE_TEXT,
            Parameter_Type::BINARY, Parameter_Type::LARGE_OBJECT => self::TYPE_BLOB,
        };
    }
}