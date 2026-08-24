<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->dateTime('due_date');
            $table->text('note')->nullable();
            $table->boolean('is_done')->default(false);
            $table->dateTime('done_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'is_done']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_follow_ups');
    }
};
