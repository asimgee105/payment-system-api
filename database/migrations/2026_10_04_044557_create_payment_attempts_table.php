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
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('attempt_number');

            $table->string('status');

            $table->string('gateway_request_id')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->json('gateway_response')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'payment_id',
                'attempt_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
