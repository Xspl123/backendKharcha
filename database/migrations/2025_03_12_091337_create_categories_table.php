<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name', 255);
        $table->text('description')->nullable();
        $table->enum('type', ['Income', 'Expense'])->default('Expense');
        $table->unsignedInteger('sort_order')->default(0);
        $table->decimal('total_amount', 10, 2)->default(0.00);
        $table->decimal('budget', 10, 2)->nullable();
        $table->unsignedBigInteger('user_id'); // Define user_id without foreign key first
        $table->timestamps();
        $table->softDeletes();
    });

    // Now add the foreign key separately
    Schema::table('categories', function (Blueprint $table) {
        $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
