<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

/**
 * Encapsulates the execution mode that is shared between the connection and its statements.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class Execution_Mode
{
    private bool $is_auto_commit_enabled = true;
    public function enable_auto_commit(): void
    {
        $this->is_auto_commit_enabled = true;
    }
    public function disable_auto_commit(): void
    {
        $this->is_auto_commit_enabled = false;
    }
    public function is_auto_commit_enabled(): bool
    {
        return $this->is_auto_commit_enabled;
    }
}