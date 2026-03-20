<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

/**
 * Describes the SQL data type of a prepared-statement bound parameter.
 *
 * Pass a case of this enum as the third argument to Connection::execute_query() /
 * execute_statement() (or as values in the $types array) so that DBAL can apply
 * the correct PDO type constant and any necessary platform-specific encoding.
 *
 * @since 3.0
 */
enum Parameter_Type
{
    /**
     * Represents the SQL NULL data type.
     *
     * Maps to PDO::PARAM_NULL. Use when the value must be stored as SQL NULL.
     */
    case NULL;

    /**
     * Represents the SQL INTEGER data type.
     *
     * Maps to PDO::PARAM_INT. Use for PHP int values or IDs.
     *
     * @since 3.0
     */
    case INTEGER;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     *
     * Maps to PDO::PARAM_STR. This is the default type for most scalar values.
     *
     * @since 3.0
     */
    case STRING;

    /**
     * Represents the SQL large object (LOB / BLOB / CLOB) data type.
     *
     * Maps to PDO::PARAM_LOB. The value should be a PHP resource or string
     * containing binary data. The driver may stream the value.
     *
     * @since 3.0
     */
    case LARGE_OBJECT;

    /**
     * Represents a boolean data type.
     *
     * Maps to PDO::PARAM_BOOL. DBAL converts PHP true/false to the
     * platform-appropriate representation (1/0, TRUE/FALSE, etc.).
     *
     * @since 3.0
     */
    case BOOLEAN;

    /**
     * Represents a binary string data type.
     *
     * Similar to STRING but signals to the driver that the value contains
     * arbitrary binary data rather than text. Use for BINARY/VARBINARY columns.
     *
     * @since 3.0
     */
    case BINARY;

    /**
     * Represents an ASCII string data type.
     *
     * Signals to Oracle (OCI8) that the value is a byte-string rather than
     * a multibyte string, avoiding unnecessary charset conversion overhead.
     *
     * @since 3.0
     */
    case ASCII;
}