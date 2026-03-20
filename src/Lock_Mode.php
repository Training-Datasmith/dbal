<?php

declare (strict_types=1);
namespace Doctrine\DBAL;

/**
 * Contains all supported lock modes.
 */
enum Lock_Mode
{
    case NONE;
    case OPTIMISTIC;
    case PESSIMISTIC_READ;
    case PESSIMISTIC_WRITE;
}