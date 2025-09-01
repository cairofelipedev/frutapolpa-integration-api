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
        Schema::table('participants', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->change();
            $table->string('cep', 20)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('neighborhood', 100)->nullable()->change();
            $table->integer('step_register')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable(false)->change();
            $table->string('cep', 20)->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('neighborhood', 100)->nullable(false)->change();
            $table->integer('step_register')->nullable(false)->change();
        });
    }
};
