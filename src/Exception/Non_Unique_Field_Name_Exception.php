<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Exception;

/**
 * Exception for a non-unique/ambiguous specified field name in a statement detected in the driver.
 */
class Non_Unique_Field_Name_Exception extends Server_Exception
{
}