<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A7's In-App Notification Center: "a Notification record per
 * (user, event) pair — type, title, body/summary, a deep link to the
 * relevant screen/record, source module, created timestamp, read/unread
 * state, read timestamp, and a 'cleared' (archived/hidden) flag distinct
 * from read/unread." Nothing is deleted purely by being read or cleared —
 * `cleared_at` only hides it from the default panel view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->string('deep_link')->nullable();
            $table->string('source_module');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'cleared_at', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
