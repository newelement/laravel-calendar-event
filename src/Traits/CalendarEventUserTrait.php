<?php

namespace Newelement\LaravelCalendarEvent\Traits;


use Newelement\LaravelCalendarEvent\Models\CalendarEvent;
use Newelement\LaravelCalendarEvent\Models\TemplateCalendarEvent;

/**
 * Trait UserTemplateCalendarEventTrait
 * @package Newelement\LaravelCalendarEvent\Traits
 */
trait CalendarEventUserTrait
{
    /**
     * Events to User, UserInterface Helper
     * @return mixed
     */
    public function calendarEvents()
    {
        return $this->hasManyThrough(
            CalendarEvent::class, TemplateCalendarEvent::class,
            'user_id', 'template_calendar_event_id', 'id'
        );
    }
}
