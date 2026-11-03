<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            // Cascade: removing a guest removes their wishlist.
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->string('link')->nullable();
            $table->text('notes')->nullable();
            // Derived in the app as image > link > text.
            $table->string('kind')->default('text');
            // Base64 data URL, up to the spec's server cap.
            $table->longText('image')->nullable();
            // The reserver's guest id. Never exposed directly;
            // the app computes reserved / reserved_by_me from it.
            $table->foreignId('reserved_by_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->timestamps();
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
