<?php

use App\Enums\DietType;
use App\Enums\Measurement;
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
        Schema::create('diet_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('diet_type', DietType::all());
        });

        // simple pivot table model not needed
        Schema::create('diet_plan_recipe', function (Blueprint $table) {
            $table->id();

            $table->foreignId('diet_plan_id')->constrained('diet_plans')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
        });

        Schema::create('macroelements', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->enum('measurement', Measurement::all());
        });

        Schema::create('user_macroelement', function (Blueprint $table) {
            $table->id();

            $table->enum('measurement', Measurement::all());
            $table->unsignedInteger('daily_rate');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('macroelement_id')->constrained('macroelements')->cascadeOnDelete();
        });

        Schema::create('ingredient_macroelement', function (Blueprint $table) {
            $table->id();

            $table->enum('measurement', Measurement::all());
            $table->unsignedInteger('quantity');
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('macroelement_id')->constrained('macroelements')->cascadeOnDelete();
        });

        // simple pivot table, model not needed
        Schema::create('recipe_tool', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
        });

        // simple pivot table, model not needed
        Schema::create('user_tool', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
        });

        // simple pivot table, model not needed
        Schema::create('diet_plan_macroelement', function (Blueprint $table) {
            $table->id();

            $table->foreignId('diet_plan_id')->constrained('diet_plans')->cascadeOnDelete();
            $table->foreignId('macroelement_id')->constrained('macroelements')->cascadeOnDelete();
        });

        Schema::create('recipe_ingredient', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('quantity');
            $table->enum('measurement', Measurement::all());
            $table->boolean('importance');

            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
        });

        Schema::create('user_ingredient', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('quantity');
            $table->date('expiry_date');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ingredient');
        Schema::dropIfExists('recipe_ingredient');
        Schema::dropIfExists('diet_plan_macroelement');
        Schema::dropIfExists('user_tool');
        Schema::dropIfExists('recipe_tool');
        Schema::dropIfExists('ingredient_macroelement');
        Schema::dropIfExists('user_macroelement');
        Schema::dropIfExists('macroelements');
        Schema::dropIfExists('diet_plan_recipe');
        Schema::dropIfExists('diet_plans');
    }
};
