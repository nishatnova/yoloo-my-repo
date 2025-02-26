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
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->string('job_title'); 
            $table->string('location')->nullable(); 
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('role')->nullable(); 
            $table->text('about_job')->nullable(); 
            $table->json('responsibilities')->nullable(); 
            $table->json('requirements')->nullable(); 
            $table->dateTime('application_deadline')->nullable(); 
            $table->string('cover_image')->nullable(); 
            $table->tinyInteger('status')->default(1)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};
