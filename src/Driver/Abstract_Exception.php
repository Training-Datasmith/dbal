<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Exception as BaseException;
use Throwable;
/**
 * Abstract base implementation of the {@see DriverException} interface.
 */
abstract class Abstract_Exception extends Base_Exception implements Exception
{
    /**
     * @param string         $message  The driver error message.
     * @param string|null    $sqlState The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int            $code     The driver specific error code if any.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(string $message, private readonly ?string $sql_state = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    public function get_sql_state(): ?string
    {
        return $this->sql_state;
    }
}