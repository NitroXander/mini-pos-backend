<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opening_notes', 500)->nullable();
            $table->string('closing_notes', 500)->nullable();
            $table->unsignedInteger('sales_count');
            $table->unsignedBigInteger('revenue_minor');
            $table->unsignedBigInteger('cogs_minor');
            $table->bigInteger('gross_profit_minor');
            $table->unsignedInteger('void_count')->default(0);
            $table->json('stock_summary');
            $table->timestamps();

            $table->unique(['shop_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_reports');
    }
};
