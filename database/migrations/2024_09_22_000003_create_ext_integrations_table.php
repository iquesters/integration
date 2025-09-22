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
        Schema::create('ext_integrations', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->foreignId('org_inte_id')->constrained('organisation_integration_metas')->onDelete('cascade');
            $table->string('ext_id');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
        });

        Schema::create('ext_integration_metas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_parent')->unsigned();
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->foreign('ref_parent')->references('id')->on('ext_integrations')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_integration_metas');
        Schema::dropIfExists('ext_integrations');
    }
};