<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id');
            $table->unsignedBigInteger('user_id');

            // `endpoint` can be a long URL (some browsers use very long FCM
            // endpoints), so it's stored as TEXT — MySQL can't put a UNIQUE
            // index directly on a TEXT column, hence the sha256 hash column
            // below, which is what the uniqueness constraint actually uses.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64);
            $table->string('public_key');   // p256dh
            $table->string('auth_token');   // auth
            $table->string('content_encoding')->default('aesgcm');

            $table->timestamps();

            $table->unique(['user_id', 'endpoint_hash'], 'push_subs_user_endpoint_unique');
            $table->index('org_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
