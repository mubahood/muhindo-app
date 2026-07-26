<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A service offered on the portfolio site ("what I do"). */
class Service extends Model
{
    protected $fillable = ['title', 'description', 'icon', 'sort_order'];
}
