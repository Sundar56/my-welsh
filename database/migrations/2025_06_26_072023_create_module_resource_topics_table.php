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
        Schema::create('module_resource_topics', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('module_resource_id')->index()->unsigned();
            $table->foreign('module_resource_id')->references('id')->on('module_resources')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('resource_topic')->nullable();
            $table->enum('resource_type', ['0', '1', '2', '3'])
                ->default('0')
                ->comment('0 - video, 1 - pdf, 2 - audio, 3 - other');
            $table->longText('description')->nullable();
            $table->string('resource_path')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_resource_topics', function (Blueprint $table) {
            if (Schema::hasColumn('module_resource_topics', 'module_resource_id')) {
                $table->dropForeign(['module_resource_id']);
                $table->dropIndex(['module_resource_id']);
            }
        });
        Schema::dropIfExists('module_resource_topics');
    }
};
