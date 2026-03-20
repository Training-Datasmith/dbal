<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a foreign key constraint violation detected in the driver.
 */
class Foreign_Key_Constraint_Violation_Exception extends Constraint_Violation_Exception
{
}