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
        Schema::create('modules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->index();
            $table->string('main_module')->nullable();
            $table->string('sub_module')->nullable();
            $table->text('icon')->nullable();
            $table->integer('order')->index()->default(1);
            $table->tinyInteger('type')->default(0);
            $table->tinyInteger('is_mainmodule')->default(0);
            $table->tinyInteger('is_submodule')->default(0);
            $table->string('frontend_slug')->index();
            $table->string('cy_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
