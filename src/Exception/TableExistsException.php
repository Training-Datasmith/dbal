<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for an already existing table referenced in a statement detected in the driver.
 */
class Table_Exists_Exception extends Database_Object_Exists_Exception
{
}