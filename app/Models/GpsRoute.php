<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpsRoute extends Model
{
    protected $fillable = ['code', 'name', 'speed_limit_kmh', 'planned_polyline_json'];

    protected $casts = [
        'speed_limit_kmh' => 'float',
        'planned_polyline_json' => 'array',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(GpsSession::class);
    }
}
