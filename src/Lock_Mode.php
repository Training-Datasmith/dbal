<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

/**
 * Defines all supported locking strategies for database row access.
 *
 * Lock modes control concurrency behaviour when reading and writing rows:
 *
 * - NONE:             No locking. Suitable for read-only operations that do not require
 *                     consistency guarantees across multiple reads.
 * - OPTIMISTIC:       Version-based optimistic locking (ORM only). The row is not locked at
 *                     read time; a version check is performed at write time and an exception
 *                     is thrown if the row was modified by another process since it was read.
 * - PESSIMISTIC_READ: Issues a SELECT … FOR SHARE (or equivalent). Other transactions can
 *                     still read the rows but cannot modify them until this lock is released.
 * - PESSIMISTIC_WRITE:Issues a SELECT … FOR UPDATE (or equivalent). No other transaction can
 *                     read or modify the locked rows until this lock is released.
 *
 * @since 3.0
 */
enum Lock_Mode
{
    /**
     * No locking strategy — plain SELECT without any lock clause.
     */
    case NONE;

    /**
     * Optimistic locking via a version column (ORM-level concern; DBAL does not enforce it).
     */
    case OPTIMISTIC;

    /**
     * Shared read lock (SELECT … FOR SHARE). Prevents concurrent writes.
     */
    case PESSIMISTIC_READ;

    /**
     * Exclusive write lock (SELECT … FOR UPDATE). Prevents concurrent reads and writes.
     */
    case PESSIMISTIC_WRITE;
}