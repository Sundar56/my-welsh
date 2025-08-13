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
        Schema::create('parent_playlists', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('parent_id')->index()->unsigned();
            $table->foreign('parent_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('playlist_id')->index()->unsigned();
            $table->foreign('playlist_id')->references('id')->on('playlists')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_playlists', function (Blueprint $table) {
            if (Schema::hasColumn('parent_playlists', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
            }
            if (Schema::hasColumn('parent_playlists', 'playlist_id')) {
                $table->dropForeign(['playlist_id']);
                $table->dropIndex(['playlist_id']);
            }
        });
        Schema::dropIfExists('parent_playlists');
    }
};
