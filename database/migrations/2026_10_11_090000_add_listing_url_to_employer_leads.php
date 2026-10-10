<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_leads', fn (Blueprint $table) => $table->string('listing_url')->nullable()->after('job_url'));
    }

    public function down(): void
    {
        Schema::table('employer_leads', fn (Blueprint $table) => $table->dropColumn('listing_url'));
    }
};
