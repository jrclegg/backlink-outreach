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
        Schema::create('discovery_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->string('footprint_pattern');
            $table->string('status')->default('pending');
            $table->integer('results_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->foreign('discovery_query_id')->references('id')->on('discovery_queries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['discovery_query_id']);
        });
        Schema::dropIfExists('discovery_queries');
    }
};
