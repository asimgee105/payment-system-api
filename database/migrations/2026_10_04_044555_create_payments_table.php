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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('payment_reference')
                ->unique();

            $table->string('idempotency_key', 100);

            $table->unsignedBigInteger('amount');

            $table->char('currency', 3)
                ->default('USD');

            $table->string('status')
                ->default('pending');

            $table->string('gateway')
                ->default('fake');

            $table->string('gateway_payment_id')
                ->nullable();

            $table->text('failure_reason')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'idempotency_key',
            ]);

            $table->index('status');
            $table->index('gateway_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
