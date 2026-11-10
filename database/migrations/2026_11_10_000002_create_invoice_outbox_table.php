<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_outbox', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuid('invoice_id');
            $table->timestamp('created_at');
            $table->timestamp('dispatched_at')->nullable()->index();

            $table->foreign('invoice_id')->references('id')->on('invoices');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_outbox');
    }
};
