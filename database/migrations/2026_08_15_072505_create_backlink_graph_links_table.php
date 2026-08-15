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
        Schema::create('backlink_graph_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_url')->unique();
            $table->string('source_domain')->indexed();
            $table->string('target_url');
            $table->string('target_domain')->indexed();
            $table->string('anchor_text')->nullable();
            $table->timestamp('discovered_at');
            $table->foreignId('discovery_query_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['source_url', 'target_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_graph_links');
    }
};
