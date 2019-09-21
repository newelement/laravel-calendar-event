<?php

namespace Newelement\LaravelCalendarEvent\Interfaces;


/**
 * Interface UserInterface
 * @package Newelement\LaravelCalendarEvent\Interfaces
 */
interface UserInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\hasManyThrough
     */
    public function calendarEvents();
}
