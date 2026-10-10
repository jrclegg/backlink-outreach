<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_campaigns', function (Blueprint $table) {
            $table->string('cadence')->default('weekly');
            $table->date('starts_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employer_campaigns', fn (Blueprint $table) => $table->dropColumn(['cadence', 'starts_on']));
    }
};
