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
        Schema::create('client_merges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('primary_client_id');
            $table->uuid('merged_client_id');
            $table->uuid('merged_by');
            $table->json('merge_data')->nullable();
            $table->timestamps();

            // Foreign key constraints would be handled by the application
            // since clients are in different microservices
            $table->index('primary_client_id');
            $table->index('merged_client_id');
            $table->index('merged_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_merges');
    }
};
