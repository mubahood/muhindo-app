<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per day, rebuilt from the raw tables by `analytics:rollup`. */
class AnalyticsDaily extends Model
{
    protected $table = 'analytics_daily';

    protected $fillable = [
        'date', 'visitors', 'new_visitors', 'visits', 'page_views', 'bounces',
        'engaged_seconds', 'signups', 'enrollments', 'orders', 'inquiries',
        'revenue', 'by_channel', 'by_country', 'by_device',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue' => 'decimal:2',
            'by_channel' => 'array',
            'by_country' => 'array',
            'by_device' => 'array',
        ];
    }
}
