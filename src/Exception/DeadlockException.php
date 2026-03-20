<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a deadlock error of a transaction detected in the driver.
 */
class Deadlock_Exception extends Server_Exception implements Retryable_Exception
{
}