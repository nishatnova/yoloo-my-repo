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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('service_title');
            $table->text('about')->nullable();
            $table->json('estate_details'); // Store array of objects [{title, description}]
            $table->json('included_services'); // Store array ["Photography", "Catering"]
            $table->decimal('price', 10, 2);
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('cover_image')->nullable(); // Store image path
            $table->tinyInteger('active_status')->default(1)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
