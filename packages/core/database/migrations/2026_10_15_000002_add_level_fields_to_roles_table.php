<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->unique()->after('guard_name');
            $table->string('color', 16)->nullable()->after('level');
            $table->boolean('is_default')->default(false)->after('color');
            $table->boolean('is_system')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropUnique(['level']);
            $table->dropColumn(['level', 'color', 'is_default', 'is_system']);
        });
    }
};
