<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->string('return_type');
            $table->string('period');
            $table->string('status')->default('draft');
            $table->json('data_snapshot')->nullable();
            $table->dateTime('filed_at')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'return_type', 'period']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_returns');
    }
};
