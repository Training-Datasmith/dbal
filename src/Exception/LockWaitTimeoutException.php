<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a lock wait timeout error of a transaction detected in the driver.
 */
class Lock_Wait_Timeout_Exception extends Server_Exception implements Retryable_Exception
{
}