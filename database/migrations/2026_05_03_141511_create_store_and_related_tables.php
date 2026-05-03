<?php

use App\Enums\IngredientCategory;
use App\Enums\Measurement;
use App\Enums\ToolType;
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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('address');
            $table->string('city');
        });

        Schema::create('shopping_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->date('generation_date')->useCurrent();
        });

        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id();

            $table->double('price');

            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shopping_plan_id')->constrained('shopping_plans')->cascadeOnDelete();
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ToolType::all());
        });
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();

            $table->enum('category', IngredientCategory::all());
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->enum('measurement', Measurement::all());

            $table->foreignId('tool_id')->nullable()->constrained('tools')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->cascadeOnDelete();
        });

        Schema::create('store_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shopping_plan_id')->constrained('shopping_plans')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('shopping_carts');
        Schema::dropIfExists('shopping_plans');
        Schema::dropIfExists('stores');
    }
};
