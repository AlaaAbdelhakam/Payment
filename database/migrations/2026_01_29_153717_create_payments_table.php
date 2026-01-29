<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);                 
            $table->string('reference')->nullable()->index(); 
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 10, 2);
            $table->text('payment_url')->nullable();
            $table->json('raw')->nullable();               
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};