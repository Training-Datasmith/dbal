<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

/** @internal */
final class Fetch_Utils
{
    /** @throws Exception */
    public static function fetch_one(Result $result): mixed
    {
        $row = $result->fetch_numeric();
        if ($row === false) {
            return false;
        }
        return $row[0];
    }
    /**
     * @return list<list<mixed>>
     *
     * @throws Exception
     */
    public static function fetch_all_numeric(Result $result): array
    {
        $rows = [];
        while (($row = $result->fetch_numeric()) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     */
    public static function fetch_all_associative(Result $result): array
    {
        $rows = [];
        while (($row = $result->fetch_associative()) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * @return list<mixed>
     *
     * @throws Exception
     */
    public static function fetch_first_column(Result $result): array
    {
        $rows = [];
        while (($row = $result->fetch_one()) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
}