<?php

namespace Newelement\LaravelCalendarEvent\Enums;

/**
 * Class RecurringFrequenceType
 * @package Newelement\LaravelCalendarEvent\Enums
 */
abstract class RecurringFrequenceType
{
    const DAY   = 'day';
    const WEEK  = 'week';
    const MONTH = 'month';
    const YEAR  = 'year';
    const NTHWEEKDAY  = 'nthweekday';
}
