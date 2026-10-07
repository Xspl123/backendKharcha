<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema()
    {
        return Schema::connection(config('tenancy.database.central_connection', config('database.default')));
    }

    public function up(): void
    {
        $this->schema()->create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 100)->nullable();
            $table->string('device_name')->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('logged_in_at')->useCurrent();
            $table->timestamps();
            $table->index(['user_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('login_histories');
    }
};
