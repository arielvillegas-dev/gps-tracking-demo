<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->decimal('speed_limit_kmh', 5, 2)->default(60.00);
            $table->json('planned_polyline_json');
            $table->timestamps();
        });

        Schema::create('gps_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gps_route_id')->constrained('gps_routes')->cascadeOnDelete();
            $table->string('unit_code', 32);
            $table->string('driver_name', 100);
            $table->string('status', 24)->default('active');
            $table->decimal('total_distance_km', 8, 3)->default(0);
            $table->unsignedInteger('stops_count')->default(0);
            $table->unsignedInteger('speed_alerts_count')->default(0);
            $table->unsignedInteger('filtered_noise_count')->default(0);
            $table->timestamps();
        });

        Schema::create('gps_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gps_session_id')->constrained('gps_sessions')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 2)->default(0);
            $table->decimal('accuracy_meters', 6, 2)->default(10);
            $table->decimal('heading', 5, 2)->default(0);
            $table->boolean('is_stop')->default(false);
            $table->boolean('is_speed_alert')->default(false);
            $table->decimal('route_deviation_meters', 8, 2)->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['gps_session_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_pings');
        Schema::dropIfExists('gps_sessions');
        Schema::dropIfExists('gps_routes');
    }
};
