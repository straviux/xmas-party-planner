<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            // Spec field `party` (the party name).
            $table->string('name');
            // Budget stays a string: the old store keeps a formatted
            // budget string, not a number.
            $table->string('budget')->nullable();
            $table->date('date')->nullable();
            $table->string('invite_code', 6)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
