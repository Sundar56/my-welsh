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
        Schema::create('module_resources', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('resource_id')->index()->unsigned();
            $table->foreign('resource_id')->references('id')->on('learning_resources')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('module_name')->nullable();
            $table->string('module_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_resources', function (Blueprint $table) {
            if (Schema::hasColumn('module_resources', 'resource_id')) {
                $table->dropForeign(['resource_id']);
                $table->dropIndex(['resource_id']);
            }
        });
        Schema::dropIfExists('module_resources');
    }
};
