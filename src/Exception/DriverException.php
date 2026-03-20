<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

use function assert;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query;
/**
 * Base class for all errors detected in the driver.
 */
class Driver_Exception extends \Exception implements Exception, Driver\Exception
{
    /**
     * @param Driver\Exception $driverException The DBAL driver exception to chain.
     * @param Query|null       $query           The SQL query that triggered the exception, if any.
     */
    public function __construct(Driver\Exception $driver_exception, private readonly ?Query $query)
    {
        if ($query !== null) {
            $message = 'An exception occurred while executing a query: ' . $driver_exception->get_message();
        } else {
            $message = 'An exception occurred in the driver: ' . $driver_exception->get_message();
        }
        parent::__construct($message, $driver_exception->get_code(), $driver_exception);
    }
    public function get_sql_state(): ?string
    {
        $previous = $this->get_previous();
        assert($previous instanceof Driver\Exception);
        return $previous->get_sql_state();
    }
    public function get_query(): ?Query
    {
        return $this->query;
    }
}