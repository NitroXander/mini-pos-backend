<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->unsignedBigInteger('sell_price_minor');
            $table->unsignedBigInteger('cost_minor');
            $table->string('unit', 32)->default('pcs');
            $table->string('barcode', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('stock_on_hand', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'sku']);
            $table->index(['shop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
