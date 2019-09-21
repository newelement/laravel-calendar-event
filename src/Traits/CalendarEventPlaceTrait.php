<?php

namespace Newelement\LaravelCalendarEvent\Traits;


use Newelement\LaravelCalendarEvent\Models\CalendarEvent;
use Newelement\LaravelCalendarEvent\Models\TemplateCalendarEvent;

/**
 * Trait PlaceTemplateCalendarEventTrait
 * @package Newelement\LaravelCalendarEvent\Traits
 */
trait CalendarEventPlaceTrait
{
    /**
     * Events to Place, PlaceInterface Helper
     * @return mixed
     */
    public function calendarEvents()
    {
        return $this->hasManyThrough(
            CalendarEvent::class, TemplateCalendarEvent::class,
            'place_id', 'template_calendar_event_id', 'id'
        );
    }
}
