<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('field_key');
            $table->string('label');
            $table->enum('field_type', ['text', 'number', 'date', 'select'])->default('text');
            $table->json('options')->nullable(); // only used when field_type = select
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['org_id', 'field_key']);
            $table->unique(['user_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_custom_fields');
    }
};