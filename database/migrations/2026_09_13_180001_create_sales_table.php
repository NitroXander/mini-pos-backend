<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid')->unique();
            $table->unsignedInteger('number');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('cashier_name');
            $table->timestamp('sold_at');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('payment_method', 32)->default('cash');
            $table->string('status', 32)->default('completed');
            $table->boolean('stock_warning')->default(false);
            $table->timestamps();

            $table->unique(['shop_id', 'number']);
            $table->index(['shop_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
