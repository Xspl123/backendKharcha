<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_leads');
    }
};
