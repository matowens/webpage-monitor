<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webpage_monitors', function (Blueprint $table) {
            $table->unsignedInteger('interval_minutes')->nullable()->after('is_active');
            $table->timestamp('next_run_at')->nullable()->after('interval_minutes');
            $table->timestamp('last_run_at')->nullable()->after('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('webpage_monitors', function (Blueprint $table) {
            $table->dropColumn([
                'interval_minutes',
                'next_run_at',
                'last_run_at',
            ]);
        });
    }
};
