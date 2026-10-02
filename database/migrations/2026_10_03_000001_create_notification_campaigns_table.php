<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('title', 255);
            $table->text('content');
            $table->text('content_html');
            $table->string('audience', 32);
            $table->json('audience_snapshot')->nullable();
            $table->boolean('popup_enabled')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->string('status', 24)->default('published');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('notification_campaigns'); }
};
