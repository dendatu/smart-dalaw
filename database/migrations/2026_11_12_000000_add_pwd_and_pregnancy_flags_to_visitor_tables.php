<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('visitor_is_pwd')->default(false)->after('visitor_age');
            $table->boolean('visitor_is_pregnant')->default(false)->after('visitor_is_pwd');
        });

        Schema::table('accompanying_visitors', function (Blueprint $table) {
            $table->boolean('is_pwd')->default(false)->after('sex');
            $table->boolean('is_pregnant')->default(false)->after('is_pwd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['visitor_is_pwd', 'visitor_is_pregnant']);
        });

        Schema::table('accompanying_visitors', function (Blueprint $table) {
            $table->dropColumn(['is_pwd', 'is_pregnant']);
        });
    }
};
