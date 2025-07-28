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
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('resource_name')->nullable();
            $table->float('monthly_fee', 10, 2)->nullable();
            $table->float('annual_fee', 10, 2)->nullable();
            $table->string('resource_reference')->nullable();
            $table->enum('type', ['0', '1', '2', '3'])
                ->default('0')
                ->comment('0 - default, 1 - trail, 2 - all, 3 - secondary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_resources');
    }
};
