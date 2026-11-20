<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpsSession extends Model
{
    protected $fillable = [
        'gps_route_id',
        'unit_code',
        'driver_name',
        'status',
        'total_distance_km',
        'stops_count',
        'speed_alerts_count',
        'filtered_noise_count',
    ];

    protected $casts = [
        'total_distance_km' => 'float',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(GpsRoute::class, 'gps_route_id');
    }

    public function pings(): HasMany
    {
        return $this->hasMany(GpsPing::class)->orderBy('recorded_at');
    }
}
