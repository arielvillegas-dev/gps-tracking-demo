<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsPing extends Model
{
    protected $fillable = [
        'gps_session_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'accuracy_meters',
        'heading',
        'is_stop',
        'is_speed_alert',
        'route_deviation_meters',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed_kmh' => 'float',
        'accuracy_meters' => 'float',
        'heading' => 'float',
        'is_stop' => 'boolean',
        'is_speed_alert' => 'boolean',
        'route_deviation_meters' => 'float',
        'recorded_at' => 'datetime',
    ];
}
