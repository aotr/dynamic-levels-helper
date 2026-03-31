<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key');
            $table->string('group')->default('general');
            $table->string('display_name')->default('');

            $table->json('value')->nullable();
            $table->json('meta')->nullable();

            $table->string('type');
            $table->integer('order')->default(1);

            $table->nullableMorphs('scope');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['key', 'scope_type', 'scope_id']);
            $table->index('group');
        });
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
