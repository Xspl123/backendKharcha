<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('org_id')->nullable()->index();
            $table->string('type', 50);
            $table->string('status', 20)->default('queued')->index();
            $table->json('filters')->nullable();
            $table->string('file_disk', 30)->default('local');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['org_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_requests');
    }
};
