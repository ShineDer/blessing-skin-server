<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('notification_campaigns', 'publicity_enabled')) {
            Schema::table('notification_campaigns', function (Blueprint $table) {
                $table->boolean('publicity_enabled')->default(false);
            });
        }

        if (!Schema::hasColumn('notification_campaigns', 'public_days')) {
            Schema::table('notification_campaigns', function (Blueprint $table) {
                $table->unsignedInteger('public_days')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_campaigns', 'public_days')) {
            Schema::table('notification_campaigns', function (Blueprint $table) {
                $table->dropColumn('public_days');
            });
        }

        if (Schema::hasColumn('notification_campaigns', 'publicity_enabled')) {
            Schema::table('notification_campaigns', function (Blueprint $table) {
                $table->dropColumn('publicity_enabled');
            });
        }
    }
};
