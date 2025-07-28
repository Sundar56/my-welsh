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
        Schema::create('parent_invites', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('playlist_id')->index()->unsigned();
            $table->foreign('playlist_id')->references('id')->on('playlists')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('parent_email')->nullable();
            $table->tinyInteger('is_invited')->default(0);
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_invites', function (Blueprint $table) {
            if (Schema::hasColumn('parent_invites', 'playlist_id')) {
                $table->dropForeign(['playlist_id']);
                $table->dropIndex(['playlist_id']);
            }
        });
        Schema::dropIfExists('parent_invites');
    }
};
