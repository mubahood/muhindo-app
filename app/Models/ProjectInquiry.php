<?php

namespace App\Models;

use App\Enums\ProjectInquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** §4.3 — a public "start a project" lead. */
class ProjectInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'user_id', 'name', 'email', 'phone', 'organisation', 'project_type',
        'budget_range', 'timeline', 'description', 'status',
        'title', 'category', 'budget_amount', 'budget_currency',
        'who_uses_it', 'success_looks_like', 'submitted_at', 'country',
    ];

    /**
     * Where clients actually are.
     *
     * A free-text country field gets "UG", "Uganda", "uganda" and "Kampala"
     * in the same column. A short list of the places work has come from,
     * plus a way out, keeps it answerable in one tap on a phone.
     */
    public const COUNTRIES = [
        'Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Burundi', 'South Sudan',
        'DR Congo', 'Ethiopia', 'Nigeria', 'Ghana', 'South Africa',
        'United Kingdom', 'United States', 'Canada', 'United Arab Emirates',
        'Somewhere else',
    ];

    /** What somebody can pick from, and what each one means in plain words. */
    public const CATEGORIES = [
        'management_system' => 'A system to run something — stock, patients, students, cases',
        'ecommerce' => 'Selling online — a shop, a marketplace, bookings',
        'mobile_app' => 'A mobile app, on its own or alongside a website',
        'data_platform' => 'Collecting and reporting on data across many places',
        'website' => 'A website that has to do more than sit there',
        'rescue' => 'Something that exists and is not working',
        'other' => 'Something else — tell me below',
    ];

    public const TIMELINES = [
        'asap' => 'As soon as you can start',
        '1_3_months' => 'Within one to three months',
        '3_6_months' => 'Three to six months out',
        'exploring' => 'No date yet — I am working out whether to do it',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: 'Not said');
    }

    public function timelineLabel(): string
    {
        return self::TIMELINES[$this->timeline] ?? ($this->timeline ?: 'Not said');
    }

    /** "UGX 12,000,000" or "$3,000" — however they chose to think about it. */
    public function budgetLabel(): string
    {
        if (! $this->budget_amount) {
            return 'Not said';
        }

        $amount = number_format((float) $this->budget_amount);

        return $this->budget_currency === 'USD' ? '$'.$amount : 'UGX '.$amount;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectInquiryStatus::class,
            'submitted_at' => 'datetime',
            'budget_amount' => 'decimal:2',
        ];
    }
}
