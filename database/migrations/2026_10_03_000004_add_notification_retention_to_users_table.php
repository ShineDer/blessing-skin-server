<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->unsignedSmallInteger('notification_retention_days')->default(0)->after('verified'); });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->dropColumn('notification_retention_days'); });
    }
};
