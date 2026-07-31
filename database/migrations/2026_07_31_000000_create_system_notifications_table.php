<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80)->default('system');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('unread')->index();
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
