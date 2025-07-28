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
        Schema::create('playlist_resources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('playlist_id')->index()->unsigned();
            $table->foreign('playlist_id')->references('id')->on('playlists')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('module_resource_topic_id')->index()->unsigned();
            $table->foreign('module_resource_topic_id')->references('id')->on('module_resource_topics')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->integer('position')->index()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlist_resources', function (Blueprint $table) {
            if (Schema::hasColumn('playlist_resources', 'playlist_id')) {
                $table->dropForeign(['playlist_id']);
                $table->dropIndex(['playlist_id']);
            }
            if (Schema::hasColumn('playlist_resources', 'module_resource_topic_id')) {
                $table->dropForeign(['module_resource_topic_id']);
                $table->dropIndex(['module_resource_topic_id']);
            }
        });
        Schema::dropIfExists('playlist_resources');
    }
};
