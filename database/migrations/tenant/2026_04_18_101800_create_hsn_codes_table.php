<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsn_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->string('hsn_code');
            $table->string('description');
            $table->decimal('gst_rate', 5, 2);
            $table->timestamps();

            $table->index(['org_id', 'hsn_code'], 'hsn_codes_org_code_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsn_codes');
    }
};
