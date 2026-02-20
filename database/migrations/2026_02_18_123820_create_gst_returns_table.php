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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('return_type', ['GSTR1', 'GSTR3B']);

            // '2026-02' format
            $table->string('period', 7);

            $table->enum('status', ['draft', 'filed'])->default('draft');

            // Filed data ka snapshot — audit trail ke liye
            $table->json('data_snapshot')->nullable();

            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            // Ek user ek period mein ek hi return file kar sakta hai
            $table->unique(['user_id', 'return_type', 'period']);
            $table->index(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_returns');
    }
};