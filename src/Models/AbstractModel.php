<?php

namespace Newelement\LaravelCalendarEvent\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * Class AbstractModel
 * @package Newelement\LaravelCalendarEvent\Models
 */
abstract class AbstractModel extends Model
{
    /**
     * @param $query
     * @return Builder
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
