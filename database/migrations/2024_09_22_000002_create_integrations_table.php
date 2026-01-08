<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->foreignId('supported_integration_id')
                ->constrained('supported_integrations')
                ->onDelete('cascade');
            $table->string('status')->default('unknown');
            $table->boolean('is_default')->default(false);
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
        });

        Schema::create('integration_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ref_parent')->constrained('integrations')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_metas');
        Schema::dropIfExists('integrations');
    }
};