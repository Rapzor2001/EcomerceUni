<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('variants')->nullable()->after('images');
            $table->jsonb('tags')->nullable()->after('variants');
            $table->string('sku')->nullable()->unique()->after('slug');
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_status', 30)->default('pending')->index()->after('payment_status');
            $table->string('shipping_carrier')->nullable()->after('shipping_address');
            $table->string('tracking_number')->nullable()->index()->after('shipping_carrier');
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_path');
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('system_settings');
        Schema::table('orders', function (Blueprint $table) { $table->dropColumn(['fulfillment_status', 'shipping_carrier', 'tracking_number', 'shipped_at', 'delivered_at']); });
        Schema::table('products', function (Blueprint $table) { $table->dropColumn(['variants', 'tags', 'sku', 'low_stock_threshold']); });
    }
};
