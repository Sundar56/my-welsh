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
        Schema::create('trail_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('user_id')->index()->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('resource_id')->index()->unsigned();
            $table->foreign('resource_id')->references('id')->on('learning_resources')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->date('trail_start_date');
            $table->date('trail_end_date');
            $table->string('trail_expired_at')->nullable();
            $table->tinyInteger('status')->default(0);
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
            if (Schema::hasColumn('subscription_history', 'resource_id')) {
                $table->dropForeign(['resource_id']);
                $table->dropIndex(['resource_id']);
            }
        });
        Schema::dropIfExists('trail_history');
    }
};
