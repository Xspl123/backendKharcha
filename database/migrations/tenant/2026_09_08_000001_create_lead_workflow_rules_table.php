<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('name');

            // v1 only supports the 'status_change' trigger — a rule fires
            // once when a lead's status transitions TO trigger_status.
            // trigger_type is stored (not hard-coded) so a future trigger
            // kind (e.g. 'stale_days') can be added without a new table.
            $table->string('trigger_type')->default('status_change');
            $table->string('trigger_status');

            // v1 only supports the 'notify_owner' action (push notification
            // to the lead's owner via the existing PushNotificationService).
            $table->string('action_type')->default('notify_owner');
            $table->string('action_message')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['org_id', 'trigger_status']);
            $table->index(['user_id', 'trigger_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_workflow_rules');
    }
};