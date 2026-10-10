<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_contacts', function (Blueprint $table) {
            $table->string('job_advert_url', 2048)->nullable();
            $table->string('job_advert_title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employer_contacts', fn (Blueprint $table) => $table->dropColumn(['job_advert_url', 'job_advert_title']));
    }
};
