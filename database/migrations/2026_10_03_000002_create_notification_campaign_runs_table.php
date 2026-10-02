<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->unsignedInteger('run_number');
            $table->string('mode', 24)->default('initial');
            $table->json('audience_snapshot')->nullable();
            $table->foreignId('source_run_id')->nullable()->constrained('notification_campaign_runs')->nullOnDelete();
            $table->foreignId('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 24)->default('running');
            $table->timestamps();
            $table->unique(['campaign_id', 'run_number']);
        });
    }
    public function down(): void { Schema::dropIfExists('notification_campaign_runs'); }
};
