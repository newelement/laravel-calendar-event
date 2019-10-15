<?php

namespace Newelement\LaravelCalendarEvent\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Newelement\LaravelCalendarEvent\Enums\RecurringFrequenceType;
use Newelement\LaravelCalendarEvent\Interfaces\CalendarEventInterface;
use Newelement\LaravelCalendarEvent\Interfaces\PlaceInterface;
use Newelement\LaravelCalendarEvent\Interfaces\UserInterface;

/**
 * Class CalendarEvent
 * @package T1k3\LaravelCalendarEvent\Models
 */
class CalendarEvent extends AbstractModel implements CalendarEventInterface
{
    use SoftDeletes;

    /**
     * Fillable
     * @var array
     */
    protected $fillable = [
        'start_datetime',
        'end_datetime',
    ];

    /**
     * Attribute Casting
     * @var array
     */
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /**
     * TemplateCalendarEvent
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function template()
    {
        return $this->belongsTo(TemplateCalendarEvent::class, 'template_calendar_event_id');
    }

    /**
     * Calendar event (template) data - input is different
     * @param array $attributes
     * @return bool
     */
    public function dataIsDifferent(array $attributes): bool
    {
        if (isset($attributes['start_datetime'])) {
            // CalendarEvent data check
            if ($this->start_datetime->format('Y-m-d') !== $attributes['start_datetime']->format('Y-m-d')) {
                return true;
            }
            unset($attributes['start_datetime']);
        }

        // TemplateCalendarEvent data check | Skip start_datetime from template
        return !arrayIsEqualWithDB($attributes, $this->template, ['start_datetime']);
    }

    /**
     * Create CalendarEvent with Template, User, Place
     * @param array $attributes
     * @param UserInterface|null $user
     * @param PlaceInterface|null $place
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createCalendarEvent(array $attributes, UserInterface $user = null, PlaceInterface $place = null)
    {
        DB::transaction(function () use ($attributes, $user, $place, &$calendarEvent) {
            $templateCalendarEvent = $this->template()->make($attributes);

            if ($templateCalendarEvent->user() !== null && $user !== null) {
                $templateCalendarEvent->user()->associate($user);
            }
            if ($templateCalendarEvent->place() !== null && $place !== null) {
                $templateCalendarEvent->place()->associate($place);
            }

            $templateCalendarEvent->save();

            $calendarEvent = $this->make($attributes);
            $calendarEvent->template()->associate($templateCalendarEvent);
            $calendarEvent->save();
        });

        return $calendarEvent;
    }

    /**
     * Update CalendarEvent
     * @param array $attributes
     * @param UserInterface|null $user
     * @param PlaceInterface|null $place
     * @return mixed
     */
    public function updateCalendarEvent(array $attributes, UserInterface $user = null, PlaceInterface $place = null)
    {
        DB::transaction(function () use ($attributes, $user, $place, &$calendarEventNew) {
            $calendarEventNew = $this->createCalendarEvent(
                array_merge(
                    $this->template->toArray(),
                    [
                        'start_datetime' => $this->start_datetime,
                        'end_datetime' => $this->end_datetime,
                    ],
                    $attributes
                ),
                $user,
                $place
            );

            $templateCalendarEvent = $calendarEventNew->template->parent()->associate($this->template);
            $templateCalendarEvent->save();

            if ($this->template->is_recurring && $calendarEventNew->template->is_recurring) {
                $this->template->update([
                    'end_of_recurring' => $this->start_datetime,
                ]);
            } else {
                $calendarEventNew->template->update([
                    'end_of_recurring' => null,
                ]);
            }
//            $this->delete();
            $this->deleteCalendarEvent($this->template->is_recurring && $templateCalendarEvent->is_recurring);
        });

        return $calendarEventNew;
    }

    /**
     * Edit\Update calendar event with data check
     * @param array $attributes
     * @param UserInterface|null $user
     * @param PlaceInterface|null $place
     * @return null|CalendarEvent
     */
    public function editCalendarEvent(array $attributes, UserInterface $user = null, PlaceInterface $place = null)
    {
        if ($this->dataIsDifferent($attributes)
            || ($user ? $user->id : null) != $this->template->user_id
            || ($place ? $place->id : null) != $this->template->place_id
        ) {
            return $this->updateCalendarEvent($attributes, $user, $place);
        }
        return null;
    }

    /**
     * Delete calendar event
     * @param bool|null $isRecurring
     * @return bool|null
     */
    public function deleteCalendarEvent(bool $isRecurring = null)
    {
        DB::transaction(function () use ($isRecurring, &$isDeleted) {
            if ($isRecurring === null) {
                $isRecurring = $this->template->is_recurring;
            }

            if ($this->template->is_recurring && $isRecurring) {
                $this->template->update(['end_of_recurring' => $this->start_datetime]);

                $nextCalendarEvents = $this->template->events()->where('start_datetime', '>', $this->start_datetime)->get();
                foreach ($nextCalendarEvents as $nextCalendarEvent) {
                    $nextCalendarEvent->delete();
                }

                if ($this->template->start_datetime == $this->start_datetime) {
                    $this->template->delete();
                }
            }

            if (!$this->template->is_recurring) {
                $this->template->delete();
            }

            $isDeleted = $this->delete();
        });
        return $isDeleted;
    }

    /**
     * Show (potential) CalendarEvent of the month
     * @param \DateTimeInterface $date
     * @return Collection|static
     */
    public static function showPotentialCalendarEventsOfMonth(\DateTimeInterface $date)
    {
        $endOfRecurring = $date->lastOfMonth()->hour(23)->minute(59)->second(59);
        $month = str_pad($endOfRecurring->month, 2, '0', STR_PAD_LEFT);
        $templateCalendarEvents = self::getMonthlyEvents($endOfRecurring);
       
        $calendarEvents = collect();
        foreach ($templateCalendarEvents as $templateCalendarEvent) {
            
            $calendarEvents = $calendarEvents->merge(
                $templateCalendarEvent->events()->whereMonth('start_datetime', $month)->get()
            );
           
            $calendarEventTmpLast = $templateCalendarEvent->events()->orderBy('start_datetime', 'desc')->first();
            $dateNext = self::getNextDateFromTemplate($templateCalendarEvent, $calendarEventTmpLast, $date);
            
            while ($dateNext !== null && $dateNext->year == $date->year && $dateNext->month <= (int)$month) {
                $diffInDays = $templateCalendarEvent->start_datetime->diffInMinutes($templateCalendarEvent->end_datetime);
                $dateNextEnd = clone($dateNext);
                $dateNextEnd = $dateNextEnd->addMinutes($diffInDays);

                $calendarEventNotExists = (new CalendarEvent())->make([
                    'start_datetime' => $dateNext,
                    'end_datetime' => $dateNextEnd,
                ]);
                $calendarEventNotExists->is_not_exists = true;
                $calendarEventNotExists->template()->associate($templateCalendarEvent);

                if ($calendarEventNotExists->start_datetime->month === (int)$month && !$templateCalendarEvent->events()->where('start_datetime', $dateNext)->first()) {
                    $calendarEvents = $calendarEvents->merge(collect([$calendarEventNotExists]));
                }

                $dateNext = $templateCalendarEvent->getNextCalendarEventStartDateTime($calendarEventNotExists->start_datetime);
            }
        }

        return $calendarEvents;
    }

    private static function getNextDateFromTemplate(TemplateCalendarEvent $templateCalendarEvent, CalendarEvent $calendarEventTmpLast, \DateTimeInterface $date)
    {
        $dateNext = null;
        // TODO Refactor: OCP, Strategy
        if ($calendarEventTmpLast) {
            switch ($templateCalendarEvent->frequence_type_of_recurring) {
                case RecurringFrequenceType::DAY:
                    $diff = $calendarEventTmpLast->template->frequence_number_of_recurring;
                    $dateNext = $calendarEventTmpLast->start_datetime->addDays($diff);
                    break;
                case RecurringFrequenceType::WEEK:
                    $diff = $date->firstOfMonth()->diffInWeeks($calendarEventTmpLast->start_datetime);
                    $dateNext = $calendarEventTmpLast->start_datetime->addWeeks($diff);
                    break;
                case RecurringFrequenceType::MONTH:
                    $diff = $date->firstOfMonth()->diffInMonths($calendarEventTmpLast->start_datetime);
                    $dateNext = $calendarEventTmpLast->start_datetime->addMonths($diff);
                    break;
                case RecurringFrequenceType::YEAR:
                    $diff = $date->firstOfMonth()->diffInYears($calendarEventTmpLast->start_datetime);
                    $dateNext = $calendarEventTmpLast->start_datetime->addYears($diff);
                    break;
                case RecurringFrequenceType::NTHWEEKDAY:
                    $diff = $date->firstOfMonth()->diffInMonths($calendarEventTmpLast->start_datetime);
                    $nextMonth = $calendarEventTmpLast->start_datetime->addMonths($diff);

                    $weekdays = getWeekdaysInMonth(
                        $calendarEventTmpLast->start_datetime->format('l'),
                        $nextMonth
                    );
                    $dateNext = $weekdays[$calendarEventTmpLast->start_datetime->weekOfMonth - 1];
                    break;
            }
        }

        return $dateNext;
    }

    private static function getMonthlyEvents(\DateTimeInterface $endOfRecurring): Collection
    {
        $month = str_pad($endOfRecurring->month, 2, '0', STR_PAD_LEFT);

        return TemplateCalendarEvent
            ::where(function ($q) use ($month) {
                $q->where('is_recurring', false)
                    ->whereMonth('start_datetime', $month);
            })
            ->orWhere(function ($q) use ($endOfRecurring) {
                $q->where('is_recurring', true)
                    ->whereNull('end_of_recurring')
                    ->where('start_datetime', '<=', $endOfRecurring);
            })
            ->orWhere(function ($q) use ($endOfRecurring) {
                $q->where('is_recurring', true)
                    ->where('end_of_recurring', '>=', $endOfRecurring)
                    ->where('start_datetime', '<=', $endOfRecurring);
            })
            ->orWhere(function ($q) use ($endOfRecurring, $month) {
                $q->where('is_recurring', true)
                    ->whereYear('end_of_recurring', $endOfRecurring->year)
                    ->whereMonth('end_of_recurring', $month)
                    ->where('start_datetime', '<=', $endOfRecurring);
            })
            ->with('events')
            ->get();
    }
}
