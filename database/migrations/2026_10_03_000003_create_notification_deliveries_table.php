<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_run_id')->constrained('notification_campaign_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->uuid('notification_id')->nullable();
            $table->boolean('eligible')->default(true);
            $table->boolean('delivered')->default(false);
            $table->boolean('popup_seen')->default(false);
            $table->boolean('visible')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['campaign_run_id', 'user_id']);
            $table->index(['user_id', 'visible']);
        });
    }
    public function down(): void { Schema::dropIfExists('notification_deliveries'); }
};
