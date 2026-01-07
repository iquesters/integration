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
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'supported_integration_id'],
                'user_integration_unique'
            );
        });

        Schema::create('integration_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ref_parent')->nullable()->constrained('integrations')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by');
            $table->bigInteger('updated_by');
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