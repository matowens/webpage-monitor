<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add durable queued-execution claim fields without relying on vendor-specific schema features.
     */
    public function up(): void
    {
        Schema::table('webpage_monitors', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable();
            $table->string('claim_token')->nullable();
        });
    }

    /**
     * Remove the queued-execution claim fields.
     */
    public function down(): void
    {
        Schema::table('webpage_monitors', function (Blueprint $table) {
            $table->dropColumn([
                'claimed_at',
                'claim_token',
            ]);
        });
    }
};
