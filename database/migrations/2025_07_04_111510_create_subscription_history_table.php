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
        Schema::create('subscription_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('type_id')->index()->unsigned();
            $table->foreign('type_id')->references('id')->on('user_subscription')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('subscription_amount')->nullable();
            $table->date('subscription_start_date');
            $table->date('subscription_end_date');
            $table->integer('subscription_duration')->nullable();
            $table->enum('fee_type', ['0', '1', '2'])
                ->default('0')
                ->comment('0 - default, 1 - month, 2 - annual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_history', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_history', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
            }
            if (Schema::hasColumn('subscription_history', 'type_id')) {
                $table->dropForeign(['type_id']);
                $table->dropIndex(['type_id']);
            }
        });
        Schema::dropIfExists('subscription_history');
    }
};
