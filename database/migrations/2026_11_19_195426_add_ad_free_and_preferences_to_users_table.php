<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Paid (or comped) players never see ads.
            $table->boolean('ad_free')->default(false)->after('password');
            // Gameplay settings that follow the player across devices (undo window, piece set).
            $table->json('preferences')->nullable()->after('ad_free');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ad_free', 'preferences']);
        });
    }
};
