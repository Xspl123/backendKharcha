<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_score_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('rules');
            $table->timestamps();

            // One row per org (or per user, if this account isn't
            // org-scoped) — save always upserts this single row.
            $table->unique('org_id');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_score_rules');
    }
};