<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'calculation_type' => 'automatic',
            'pricing_model' => 'per_page',
            'price_per_page' => .10,
            'billing_unit' => 'página',
            'active' => true,
        ];
    }
}
