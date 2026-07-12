<?php

namespace Rivetworks\WebpageMonitor\Enums;

/**
 * Supported saved monitor modes persisted as strings.
 */
enum MonitorType: string
{
    case Availability = 'availability';
    case Contains = 'contains';
    case Selector = 'selector';
}
