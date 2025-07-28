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
        Schema::create('stripe_webhooks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('stripe_event_id')->nullable();
            $table->string('stripe_event_type')->nullable();
            $table->string('stripe_request_id')->nullable();
            $table->string('stripe_request_idempotency_key')->nullable();
            $table->string('stripe_api_version')->nullable();
            $table->string('stripe_mode')->nullable();
            $table->string('stripe_object_id')->nullable();
            $table->string('stripe_customer_name')->nullable();
            $table->string('stripe_customer_email')->nullable();
            $table->decimal('stripe_amount', 12, 2)->nullable();
            $table->string('stripe_currency')->nullable();
            $table->string('stripe_capture_method')->nullable();
            $table->string('stripe_status')->nullable();
            $table->text('stripe_data')->nullable();
            $table->tinyInteger('webhookstatus')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhooks');
    }
};
