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
        Schema::create('discovery_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_query_id')->constrained()->cascadeOnDelete();
            $table->string('site_url');
            $table->string('page_title')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('extraction_status')->default('pending');
            $table->foreignId('prospect_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovery_results');
    }
};
