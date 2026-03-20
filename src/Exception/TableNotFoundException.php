<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for an unknown table referenced in a statement detected in the driver.
 */
class Table_Not_Found_Exception extends Database_Object_Not_Found_Exception
{
}