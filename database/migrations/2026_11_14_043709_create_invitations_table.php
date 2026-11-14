<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending email invites from people who don't have an account yet; consumed on sign-up.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('email')->index();
            $table->timestamps();

            $table->unique(['inviter_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
