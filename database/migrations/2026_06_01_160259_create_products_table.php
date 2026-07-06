<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_shopify')->unique();
                $table->json('title');
                $table->json('slug')->unique();
                $table->string('publisher')->nullable();
                $table->string('isbn')->nullable()->index();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('inventory_quantity')->default(0);
                $table->json('tags')->nullable();
                $table->json('book_data')->nullable();
                $table->string('status')->default('available');
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
