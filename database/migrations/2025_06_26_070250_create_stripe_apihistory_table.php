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
        Schema::create('stripe_apihistory', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('request_id')->nullable();
            $table->tinyInteger('livemode')->default(0);
            $table->string('type')->nullable();
            $table->string('method')->nullable();
            $table->integer('status')->nullable();
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->decimal('stripe_fee', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->text('description')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip')->nullable();
            $table->bigInteger('user_id')->index()->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('customer_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_apihistory', function (Blueprint $table) {
            if (Schema::hasColumn('stripe_apihistory', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
            }
        });
        Schema::dropIfExists('stripe_apihistory');
    }
};
