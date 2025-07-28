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
        Schema::create('playlists', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('user_id')->index()->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('resource_id')->index()->unsigned();
            $table->foreign('resource_id')->references('id')->on('learning_resources')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('playlist_name')->nullable();
            $table->tinyInteger('is_shared')->nullable();
            $table->string('playlist_reference')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            if (Schema::hasColumn('playlists', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
            }
        });
        Schema::table('playlists', function (Blueprint $table) {
            if (Schema::hasColumn('playlists', 'resource_id')) {
                $table->dropForeign(['resource_id']);
                $table->dropIndex(['resource_id']);
            }
        });

        Schema::dropIfExists('playlists');
    }
};
