<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 32)->unique()->after('id');
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('sex', 8)->nullable()->after('avatar_path');
            $table->date('birth_date')->nullable()->after('sex');
            $table->text('about')->nullable()->after('birth_date');
            $table->text('signature')->nullable()->after('about');
            $table->string('language', 8)->default('ru')->after('signature');
            $table->string('timezone', 64)->default('UTC')->after('language');
            $table->timestamp('last_activity_at')->nullable()->index()->after('timezone');
            $table->string('registered_ip', 45)->nullable()->after('last_activity_at');
            $table->boolean('is_suspicious')->default(false)->after('registered_ip');
            $table->string('suspicious_note')->nullable()->after('is_suspicious');
            $table->boolean('show_email')->default(false)->after('suspicious_note');
            $table->boolean('show_birthday')->default(true)->after('show_email');
            $table->string('messages_from', 8)->default('all')->after('show_birthday');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropIndex(['last_activity_at']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'login', 'avatar_path', 'sex', 'birth_date', 'about', 'signature', 'language', 'timezone',
                'last_activity_at', 'registered_ip', 'is_suspicious', 'suspicious_note',
                'show_email', 'show_birthday', 'messages_from',
            ]);
        });
    }
};
