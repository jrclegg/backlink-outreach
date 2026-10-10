<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_campaign_id')->constrained()->cascadeOnDelete();
            $t->string('source_job_id');
            $t->string('employer');
            $t->string('job_title');
            $t->string('job_url', 2048);
            $t->string('source');
            $t->string('country')->nullable();
            $t->dateTime('deadline')->nullable();
            $t->dateTime('first_seen')->nullable();
            $t->string('signal');
            $t->unsignedInteger('priority')->default(10);
            $t->string('rate_url', 2048)->nullable();
            $t->timestamps();
            $t->unique(['employer_campaign_id', 'source_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_leads');
    }
};
