<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C2: "A Timesheet action log recording every workflow action taken." Append-only, separate from the general audit log — spec explicitly calls this out as its own artifact (unlike B2's Employee activity log, which spec explicitly says may be unified with the general audit log). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_action_logs');
    }
};
