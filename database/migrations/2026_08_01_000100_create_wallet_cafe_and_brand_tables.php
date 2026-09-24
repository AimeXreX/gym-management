<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('theme_preset', 30)->default('midnight');
            $table->string('brand_primary', 7)->default('#6366f1');
            $table->string('brand_accent', 7)->default('#22c55e');
            $table->string('brand_tagline')->nullable();
            $table->string('ui_radius', 20)->default('soft');
            $table->string('ui_density', 20)->default('comfortable');
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->decimal('balance', 16, 2)->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['gym_id', 'member_id']);
            $table->index(['gym_id', 'status']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 16, 2);
            $table->decimal('balance_after', 16, 2);
            $table->string('reason', 40);
            $table->string('reference', 100);
            $table->nullableMorphs('source');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['gym_id', 'reference']);
            $table->index(['gym_id', 'member_id', 'created_at']);
        });

        Schema::create('cafe_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['gym_id', 'is_active', 'sort_order']);
        });

        Schema::create('cafe_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cafe_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['gym_id', 'is_active']);
        });

        Schema::create('cafe_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('order_number', 40);
            $table->decimal('total', 14, 2);
            $table->string('payment_method', 20);
            $table->string('status', 20)->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['gym_id', 'order_number']);
            $table->index(['gym_id', 'created_at', 'status']);
        });

        Schema::create('cafe_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cafe_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cafe_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('unit_price', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->index(['gym_id', 'cafe_order_id']);
        });
    }

    public function down(): void
    {
        foreach (['cafe_order_items', 'cafe_orders', 'cafe_products', 'cafe_categories', 'wallet_transactions', 'wallets'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn(['theme_preset', 'brand_primary', 'brand_accent', 'brand_tagline', 'ui_radius', 'ui_density']));
    }
};
