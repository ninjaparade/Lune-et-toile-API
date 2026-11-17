<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A shareable player id (ULID): people can add each other as friends with it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('uid')->nullable()->unique()->after('id');
        });

        DB::table('users')->whereNull('uid')->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['uid' => strtolower((string) Str::ulid())]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('uid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
