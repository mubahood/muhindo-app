<?php

namespace App\Models;

use App\Services\Catalog\CourseReadiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseModule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['course_id', 'title', 'sort_order'];

    /**
     * A module takes its lessons with it.
     *
     * The admin confirms with "Delete this module and its lessons?" and then
     * did not delete the lessons, and an import that renames a module trashes
     * the old one and orphans everything under it. Both left rows that no
     * student query returns and every direct Lesson query counts.
     *
     * The timestamp is copied rather than left to default so that restoring a
     * module brings back exactly the lessons that went down with it, and not a
     * lesson that had been deleted on its own beforehand.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $module) {
            if ($module->isForceDeleting()) {
                return; // the foreign key cascade already removed them
            }

            $module->lessons()->update(['deleted_at' => $module->deleted_at]);
        });

        static::restoring(function (self $module) {
            $module->lessons()->onlyTrashed()
                ->where('deleted_at', $module->deleted_at)->restore();
        });
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /**
     * How far this module has got, worked out from its lessons rather than
     * stored on the module.
     *
     * A second flag here would only give the module a way to disagree with
     * what is under it. A module with nothing published already vanishes from
     * the student sidebar on its own, because LearnShell counts published
     * lessons and drops a module at zero.
     *
     * @return array{planned:int, ready:int, live:int, unrecorded:int, total:int, recorded:int, percent:int}
     */
    public function readiness(): array
    {
        return CourseReadiness::tally($this->lessons);
    }
}
