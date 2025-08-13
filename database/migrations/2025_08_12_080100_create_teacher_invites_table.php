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
        Schema::create('teacher_invites', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('teacher_id')->index()->unsigned();
            $table->foreign('teacher_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('parent_id')->index()->unsigned();
            $table->foreign('parent_id')->references('id')->on('users')->constrained()
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
        Schema::table('teacher_invites', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_invites', 'teacher_id')) {
                $table->dropForeign(['teacher_id']);
                $table->dropIndex(['teacher_id']);
            }
            if (Schema::hasColumn('teacher_invites', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
            }
        });
        Schema::dropIfExists('teacher_invites');
    }
};
