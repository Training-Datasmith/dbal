<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Fetch_Utils;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\Invalid_Column_Index;
use const OCI_ASSOC;
use function oci_cancel;
use function oci_error;
use function oci_fetch_all;
use function oci_fetch_array;
use const OCI_FETCHSTATEMENT_BY_COLUMN;
use const OCI_FETCHSTATEMENT_BY_ROW;
use function oci_field_name;
use const OCI_NUM;
use function oci_num_fields;
use function oci_num_rows;
use const OCI_RETURN_LOBS;
use const OCI_RETURN_NULLS;
final readonly class Result implements Result_Interface
{
    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @param resource $statement
     */
    public function __construct(private mixed $statement)
    {
    }
    public function fetch_numeric(): array|false
    {
        return $this->fetch(OCI_NUM);
    }
    public function fetch_associative(): array|false
    {
        return $this->fetch(OCI_ASSOC);
    }
    public function fetch_one(): mixed
    {
        return Fetch_Utils::fetch_one($this);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_numeric(): array
    {
        return $this->fetch_all(OCI_NUM, OCI_FETCHSTATEMENT_BY_ROW);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_all_associative(): array
    {
        return $this->fetch_all(OCI_ASSOC, OCI_FETCHSTATEMENT_BY_ROW);
    }
    /**
     * {@inheritDoc}
     */
    public function fetch_first_column(): array
    {
        return $this->fetch_all(OCI_NUM, OCI_FETCHSTATEMENT_BY_COLUMN)[0];
    }
    public function row_count(): int
    {
        $count = oci_num_rows($this->statement);
        if ($count !== false) {
            return $count;
        }
        return 0;
    }
    public function column_count(): int
    {
        return oci_num_fields($this->statement);
    }
    public function get_column_name(int $index): string
    {
        // OCI expects a 1-based index while DBAL works with a O-based index.
        $name = @oci_field_name($this->statement, $index + 1);
        if ($name === false) {
            throw Invalid_Column_Index::new($index);
        }
        return $name;
    }
    public function free(): void
    {
        oci_cancel($this->statement);
    }
    /** @throws Exception */
    private function fetch(int $mode): mixed
    {
        $result = oci_fetch_array($this->statement, $mode | OCI_RETURN_NULLS | OCI_RETURN_LOBS);
        if ($result === false && oci_error($this->statement) !== false) {
            throw Error::new($this->statement);
        }
        return $result;
    }
    /** @return array<mixed> */
    private function fetch_all(int $mode, int $fetch_structure): array
    {
        oci_fetch_all($this->statement, $result, 0, -1, $mode | OCI_RETURN_NULLS | $fetch_structure | OCI_RETURN_LOBS);
        return $result;
    }
}