<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a unique constraint violation detected in the driver.
 */
class Unique_Constraint_Violation_Exception extends Constraint_Violation_Exception
{
}