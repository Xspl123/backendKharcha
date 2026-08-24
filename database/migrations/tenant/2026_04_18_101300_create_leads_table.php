<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('product_interest')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->text('lost_reason')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->unsignedBigInteger('po_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'status'], 'leads_org_status_index');
            $table->index(['org_id', 'owner_id'], 'leads_org_owner_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
