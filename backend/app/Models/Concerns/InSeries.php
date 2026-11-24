<?php

namespace App\Models\Concerns;

use App\Models\Series;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** للمحتوى الذي يمكن أن ينتمي لسلسلة (مرئي، صوتي، درس) */
trait InSeries
{
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }
}
