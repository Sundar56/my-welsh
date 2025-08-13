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
        Schema::create('update_setting_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('updated_by')->index()->unsigned();
            $table->foreign('updated_by')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->json('previous_record')->nullable();
            $table->json('updated_record')->nullable();
            $table->string('updated_time')->nullable();
            $table->string('ipaddress')->nullable();
            $table->string('useragent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('update_setting_history', function (Blueprint $table) {
            if (Schema::hasColumn('update_setting_history', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropIndex(['updated_by']);
            }
        });
        Schema::dropIfExists('update_setting_history');
    }
};
