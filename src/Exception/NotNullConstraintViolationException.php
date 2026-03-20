<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a NOT NULL constraint violation detected in the driver.
 */
class Not_Null_Constraint_Violation_Exception extends Constraint_Violation_Exception
{
}