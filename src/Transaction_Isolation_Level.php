<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * SQL transaction isolation levels as defined by the SQL standard.
 *
 * Isolation levels control the degree to which one transaction is isolated from
 * the effects of other concurrent transactions. Higher isolation prevents more
 * anomalies but typically incurs higher locking overhead:
 *
 * - READ_UNCOMMITTED: Lowest isolation. Dirty reads, non-repeatable reads, and
 *                     phantom reads are all possible. Rarely used in production.
 * - READ_COMMITTED:   Default on most RDBMS. Dirty reads are prevented; however
 *                     non-repeatable reads and phantom reads are still possible.
 * - REPEATABLE_READ:  All reads within a transaction see a consistent snapshot.
 *                     Phantom reads may still occur (MySQL InnoDB prevents them
 *                     via gap locks, but the SQL standard does not require it).
 * - SERIALIZABLE:     Highest isolation. Transactions are executed as if they were
 *                     serial (one after another). Prevents all anomalies but has
 *                     the highest contention risk.
 *
 * @since 3.0
 */
enum TransactionIsolationLevel
{
    /**
     * Allows dirty reads, non-repeatable reads, and phantom reads.
     * Maps to SQL "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED".
     */
    case READ_UNCOMMITTED;

    /**
     * Prevents dirty reads. Allows non-repeatable reads and phantom reads.
     * Maps to SQL "SET TRANSACTION ISOLATION LEVEL READ COMMITTED".
     */
    case READ_COMMITTED;

    /**
     * Prevents dirty reads and non-repeatable reads. Phantom reads may still occur.
     * Maps to SQL "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ".
     */
    case REPEATABLE_READ;

    /**
     * Prevents all read anomalies at the cost of highest contention.
     * Maps to SQL "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE".
     */
    case SERIALIZABLE;
}
