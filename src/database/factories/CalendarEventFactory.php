<?php

use Carbon\Carbon;
use Faker\Generator as Faker;
use Newelement\LaravelCalendarEvent\Models\CalendarEvent;

$factory = app()->make(\Illuminate\Database\Eloquent\Factory::class);

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(CalendarEvent::class, function (Faker $faker) {
    return [
        'start_datetime' => Carbon::now()->addWeek()->format('Y-m-d'),
        'end_datetime'   => Carbon::now()->addWeek()->format('Y-m-d'),
    ];
});
