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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained()->onDelete('cascade'); // Template
            $table->foreignId('package_id')->nullable()->constrained()->onDelete('cascade'); // Package
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->string('stripe_payment_id')->nullable();
            $table->string('service_booked')->nullable(); // Add service booked (Template/Package)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
