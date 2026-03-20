<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for an invalid specified field name in a statement detected in the driver.
 */
class Invalid_Field_Name_Exception extends Server_Exception
{
}