<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `first_verified_at` — момент первого подтверждения email. Смена адреса сбрасывает `email_verified_at`,
 * но аккаунт, подтверждённый хоть раз, не должен попадать под очистку неподтверждённых.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('first_verified_at')->nullable()->after('email_verified_at');
        });

        DB::table('users')->whereNotNull('email_verified_at')->update(['first_verified_at' => DB::raw('email_verified_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_verified_at');
        });
    }
};
