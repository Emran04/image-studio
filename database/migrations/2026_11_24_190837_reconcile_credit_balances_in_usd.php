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
        Schema::table('users', function (Blueprint $table): void {
            $table->bigInteger('credit_balance')->default(0);
        });
        Schema::table('image_generations', function (Blueprint $table): void {
            $table->unsignedBigInteger('credit_cost')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_generations', function (Blueprint $table): void {
            $table->dropColumn('credit_cost');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('credit_balance');
        });
    }
};
