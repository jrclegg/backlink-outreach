<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // SQLite doesn't support dropping unique constraints directly,
            // so we need to recreate the table
            // For now, we'll change the column to nullable
            $table->string('contact_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('contact_email')->nullable(false)->change();
        });
    }
};
