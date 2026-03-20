<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a write operation attempt on a read-only database element detected in the driver.
 */
class Read_Only_Exception extends Server_Exception
{
}