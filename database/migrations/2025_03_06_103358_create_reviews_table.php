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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->nullable();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade')->nullable();
            $table->decimal('rating', 2, 1);
            $table->longText('comment');
            $table->tinyInteger('status')->default(0)->nullable();
            $table->tinyInteger('home_status')->default(0)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
