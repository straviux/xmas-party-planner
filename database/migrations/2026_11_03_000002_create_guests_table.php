<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('display');
            $table->string('nickname')->nullable();
            $table->json('aliases')->nullable();
            // Hashed only, never plain text.
            $table->string('pin_hash')->nullable();
            // Self-reference: who this guest buys for. Nulls out
            // instead of cascading, so deleting a guest never
            // deletes their recipient.
            $table->foreignId('recipient_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->boolean('claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('revealed')->default(false);
            $table->timestamps();
            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
