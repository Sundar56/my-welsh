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
        Schema::create('billing_invoice_users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->bigInteger('billing_invoice_id')->nullable()->index()->unsigned();
            $table->foreign('billing_invoice_id')->references('id')->on('billing_emails')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('user_id')->nullable()->index()->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->constrained()
                ->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoice_users', function (Blueprint $table) {
            if (Schema::hasColumn('billing_invoice_users', 'billing_invoice_id')) {
                $table->dropForeign(['billing_invoice_id']);
                $table->dropIndex(['billing_invoice_id']);
            }

            if (Schema::hasColumn('billing_invoice_users', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
            }
        });
        Schema::dropIfExists('billing_invoice_users');
    }
};
