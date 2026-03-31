<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('setting_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('key');                    // Which setting changed
            $table->string('group')->default('general');

            $table->json('old_value')->nullable();   // Previous value
            $table->json('new_value')->nullable();   // New value

            $table->nullableUuidMorphs('auditable'); // Who made the change (User, Team, etc.)

            $table->string('action');                 // 'created', 'updated', 'deleted'
            $table->string('reason')->nullable();     // Why it changed
            $table->string('ip_address')->nullable(); // Requester IP
            $table->string('user_agent')->nullable(); // Browser / client info

            $table->timestamps();

            $table->index('key');
            $table->index('group');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('setting_audit_logs');
    }
};
