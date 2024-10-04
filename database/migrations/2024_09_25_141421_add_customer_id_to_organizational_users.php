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
        Schema::table('organizational_user', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('customer_id')->constrained();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizational_users', function (Blueprint $table) {
            //
        });
    }
};
