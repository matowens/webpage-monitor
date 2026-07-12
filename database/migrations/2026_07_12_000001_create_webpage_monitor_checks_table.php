<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webpage_monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webpage_monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('reachable');
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('duration_milliseconds');
            $table->unsignedInteger('body_bytes');
            $table->text('failure_message')->nullable();
            $table->boolean('assertion_passed')->nullable();
            $table->unsignedInteger('match_count')->nullable();
            $table->text('selected_content')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('change_state');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['webpage_monitor_id', 'checked_at']);
            $table->index(['webpage_monitor_id', 'change_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpage_monitor_checks');
    }
};
