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
        Schema::create('package_inquire_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_inquiry_id')->constrained('package_inquiries')->onDelete('cascade');
            $table->foreignId('photographer_application_id')->nullable()->constrained('job_applications')->onDelete('set null'); 
            $table->foreignId('decorator_application_id')->nullable()->constrained('job_applications')->onDelete('set null'); 
            $table->foreignId('catering_application_id')->nullable()->constrained('job_applications')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_inquire_staff');
    }
};
