<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'status'], 'campaigns_org_status_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
