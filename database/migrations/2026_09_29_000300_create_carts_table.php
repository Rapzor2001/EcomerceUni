<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('session_token')->nullable()->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // PostgreSQL: one open cart per customer; historical carts remain auditable.
        DB::statement("CREATE UNIQUE INDEX carts_one_active_cart_per_user ON carts (user_id) WHERE user_id IS NOT NULL AND status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
