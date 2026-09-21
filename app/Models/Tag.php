<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One word the Projects editor can offer. The vocabulary, not the tagging: a
 * project keeps the names it picked in its own JSON column, so restoring an
 * old revision cannot depend on a row still being here.
 */
class Tag extends Model
{
    protected $fillable = ['name'];
}
