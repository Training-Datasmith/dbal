<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

final readonly class ComparatorConfig
{
    public function __construct(
        private bool $detectRenamedColumns = true,
        private bool $detectRenamedIndexes = true,
        private bool $reportModifiedIndexes = true,
    ) {
    }

    public function withDetectRenamedColumns(bool $detectRenamedColumns): self
    {
        return new self(
            $detectRenamedColumns,
            $this->detectRenamedIndexes,
            $this->reportModifiedIndexes,
        );
    }

    public function getDetectRenamedColumns(): bool
    {
        return $this->detectRenamedColumns;
    }

    public function withDetectRenamedIndexes(bool $detectRenamedIndexes): self
    {
        return new self(
            $this->detectRenamedColumns,
            $detectRenamedIndexes,
            $this->reportModifiedIndexes,
        );
    }

    public function getDetectRenamedIndexes(): bool
    {
        return $this->detectRenamedIndexes;
    }

    public function withReportModifiedIndexes(bool $reportModifiedIndexes): self
    {
        return new self(
            $this->detectRenamedColumns,
            $this->detectRenamedIndexes,
            $reportModifiedIndexes,
        );
    }

    /** @internal This method is intended solely to provide an upgrade path to DBAL 5.0. */
    public function getReportModifiedIndexes(): bool
    {
        return $this->reportModifiedIndexes;
    }
}
