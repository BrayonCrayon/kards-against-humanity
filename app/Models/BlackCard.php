<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlackCard extends Model
{
    protected $guarded = [];

    /*
     ********************************
     *        Relationships         *
     ********************************
     */

    /**
     * @return BelongsTo
     */
    public function expansion()
    {
        return $this->belongsTo(Expansion::class);
    }
}
