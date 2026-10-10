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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('site_url');
            $table->string('contact_name')->nullable();
            $table->string('contact_email');
            $table->string('niche')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->default('csv_import');
            // Constrained in the discovery_queries migration, which runs after this one.
            $table->foreignId('discovery_query_id')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'contact_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
