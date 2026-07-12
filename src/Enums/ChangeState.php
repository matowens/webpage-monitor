<?php

namespace Rivetworks\WebpageMonitor\Enums;

/**
 * Stored classifications for completed saved monitor runs.
 */
enum ChangeState: string
{
    case Baseline = 'baseline';
    case Unchanged = 'unchanged';
    case Changed = 'changed';
    case NotApplicable = 'not_applicable';
    case Failed = 'failed';
}
