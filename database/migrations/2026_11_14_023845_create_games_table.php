<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('white_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('black_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_id')->nullable()->constrained('users')->nullOnDelete();
            // waiting (no opponent yet) → active → finished
            $table->string('status', 16)->default('waiting')->index();
            // UCI moves, e.g. ["e2e4","e7e5"]. Devices validate legality; the server enforces turn order.
            $table->json('moves');
            $table->string('result', 7)->nullable(); // 1-0, 0-1, 1/2-1/2
            $table->string('termination', 32)->nullable(); // checkmate, resignation, stalemate…
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
