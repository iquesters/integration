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
        Schema::create('organisation_integration', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->foreignId('integration_masterdata_id')
                ->constrained('integrations')
                ->onDelete('cascade');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['organisation_id', 'integration_masterdata_id'],
                'org_integ_unique'
            );
        });

        Schema::create('organisation_integration_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ref_parent')->nullable()->constrained('organisation_integration')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->string('meta_key');
            $table->string('meta_value');
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
        Schema::dropIfExists('organisation_integration_metas');
        Schema::dropIfExists('organisation_integration');
    }
};