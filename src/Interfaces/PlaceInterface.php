<?php

namespace Newelement\LaravelCalendarEvent\Interfaces;


/**
 * Interface PlaceInterface
 * @package Newelement\LaravelCalendarEvent\Interfaces
 */
interface PlaceInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\hasManyThrough
     */
    public function calendarEvents();
}
