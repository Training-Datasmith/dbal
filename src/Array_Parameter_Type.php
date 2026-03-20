<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

enum Array_Parameter_Type
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    case INTEGER;
    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    case STRING;
    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    case ASCII;
    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    case BINARY;
    /** @internal */
    public static function to_element_parameter_type(self $type): Parameter_Type
    {
        return match ($type) {
            self::INTEGER => Parameter_Type::INTEGER,
            self::STRING => Parameter_Type::STRING,
            self::ASCII => Parameter_Type::ASCII,
            self::BINARY => Parameter_Type::BINARY,
        };
    }
}