<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql;

/** @internal */
final readonly class Default_Table_Options
{
    public function __construct(private string $charset, private string $collation)
    {
    }
    public function get_charset(): string
    {
        return $this->charset;
    }
    public function get_collation(): string
    {
        return $this->collation;
    }
}