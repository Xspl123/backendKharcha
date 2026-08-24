<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('email_syncs');
        Schema::dropIfExists('emails');
        Schema::dropIfExists('sent_mails');
        Schema::dropIfExists('cached_emails');
        Schema::dropIfExists('user_mail_settings');
    }

    public function down(): void
    {
        //
    }
};
