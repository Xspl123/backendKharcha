<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('attribute_groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('unit')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
