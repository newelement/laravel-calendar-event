<?php

namespace Newelement\LaravelCalendarEvent\Console\Commands;


use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Newelement\LaravelCalendarEvent\Enums\RecurringFrequenceType;
use Newelement\LaravelCalendarEvent\Interfaces\CalendarEventInterface;
use Newelement\LaravelCalendarEvent\Models\CalendarEvent;
use Newelement\LaravelCalendarEvent\Models\TemplateCalendarEvent;

/**
 * Class GenerateCalendarEvent
 * @package Newelement\LaravelCalendarEvent\Console\Commands
 */
class GenerateCalendarEvent extends Command
{
    /**
     * Console command
     * @var string
     */
    protected $signature = 'generate:calendar-event {--date=}';

    /**
     * Console command description
     * @var string
     */
    protected $description = 'Generate CalendarEvent to (recurring) TemplateCalendarEvent';

    /**
     * TemplateCalendarEvent
     * @var TemplateCalendarEvent
     */
    private $templateCalendarEvent;

    /**
     * GenerateCalendarEvent constructor.
     * @param TemplateCalendarEvent $templateCalendarEvent
     */
    public function __construct(TemplateCalendarEvent $templateCalendarEvent)
    {
        parent::__construct();
        $this->templateCalendarEvent = $templateCalendarEvent;
    }

    /**
     * Handle
     */
    public function handle()
    {
        $date                   = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();
        $templateCalendarEvents = $this->templateCalendarEvent
            ->where('is_recurring', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_of_recurring')
                    ->orWhere('end_of_recurring', '>=', $date);
            })
            ->get();

        $count = 0;
        foreach ($templateCalendarEvents as $templateCalendarEvent) {
            $dateNext       = null;
            $endOfRecurring = (clone $date)->addWeek();
//          TODO Refactor: OCP, Strategy
            switch ($templateCalendarEvent->frequence_type_of_recurring) {
                case RecurringFrequenceType::DAY:
                    $startDateTime = $templateCalendarEvent->start_datetime;
                    $endOfRecurring = (clone $date)->hour($startDateTime->hour)->minute($startDateTime->minute)->second($startDateTime->second);
                    $diff           = $templateCalendarEvent->start_datetime->diffInDays($endOfRecurring);
                    $dateNext       = $templateCalendarEvent->start_datetime->addDays($diff);

                    if ($templateCalendarEvent->start_datetime->diffInDays($dateNext) % (int)$templateCalendarEvent->frequence_number_of_recurring === 0) {
                        $dateNext->addDays((int)$templateCalendarEvent->frequence_number_of_recurring - 1);
                        $endOfRecurring->addDays((int)$templateCalendarEvent->frequence_number_of_recurring - 1);
                    }
                    break;
                case RecurringFrequenceType::WEEK:
//                    $endOfRecurring = (clone $date)->addWeek();
                    $diff     = $templateCalendarEvent->start_datetime->diffInWeeks($endOfRecurring);
                    $dateNext = $templateCalendarEvent->start_datetime->addWeeks($diff);
                    break;
                case RecurringFrequenceType::MONTH:
//                    $endOfRecurring = (clone $date)->addMonth();
                    $diff     = $templateCalendarEvent->start_datetime->diffInMonths($endOfRecurring);
                    $dateNext = $templateCalendarEvent->start_datetime->addMonths($diff);
                    break;
                case RecurringFrequenceType::YEAR:
                    $startDateTime = $templateCalendarEvent->start_datetime;
                    $endOfRecurring = (clone $date)->addYear()->hour($startDateTime->hour)->minute($startDateTime->minute)->second($startDateTime->second);
                    $diff     = $templateCalendarEvent->start_datetime->diffInYears($endOfRecurring);
                    $dateNext = $templateCalendarEvent->start_datetime->addYears($diff);
                    break;
                case RecurringFrequenceType::NTHWEEKDAY:
                    $diff     = $date->firstOfMonth()->diffInMonths($templateCalendarEvent->start_datetime);

                    $nextMonth = $templateCalendarEvent->start_datetime->addMonths($diff);
                        $weekdays = getWeekdaysInMonth(
                        $templateCalendarEvent->start_datetime->format('l'),
                        $nextMonth
                    );

                    $dateNext = $weekdays[$templateCalendarEvent->start_datetime->weekOfMonth - 1];
            }

            if ($dateNext !== null
                && ($templateCalendarEvent->end_of_recurring === null
                    || $dateNext <= $templateCalendarEvent->end_of_recurring
                )
                && $dateNext >= $date
                && $dateNext <= $endOfRecurring
                && !$templateCalendarEvent->events()->withTrashed()->where('start_datetime', $dateNext)->first()
            ) {
                $calendarEvent = $templateCalendarEvent->createCalendarEvent($dateNext);
                $count++;

                $this->closure($calendarEvent);

                Log::info(
                    sprintf('
                        Generated CalendarEvent from Console:
                        template_calendar_event_id: %s,
                        calendar_event_id: %s,
                        start_datetime: %s,
                        end_datetime: %s',
                        $calendarEvent->template_calendar_event_id,
                        $calendarEvent->id,
                        $calendarEvent->start_datetime->format('Y-m-d'),
                        $calendarEvent->end_datetime->format('Y-m-d')
                    )
                );
            }
        }

        $this->info(sprintf('Generated CalendarEvent from Console | Summary: %s.', $count));
    }

    /**
     * TODO Name convention: callback()
     * Callback for calendar event created
     * @param CalendarEventInterface $calendarEvent
     * @return CalendarEventInterface
     */
    protected function closure(CalendarEventInterface $calendarEvent)
    {
        return $calendarEvent;
    }
}
