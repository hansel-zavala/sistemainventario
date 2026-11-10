<?php

namespace Database\Factories;

use App\Models\Tinta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tinta>
 */
class TintaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('INK-####-????'),
            'color' => 'Negro',
            'botellas_completas' => 2,
            'porcentaje_botella_abierta' => 0,
            'stock_minimo_botellas' => 1,
            'activo' => true,
            'creado_por' => User::factory(),
        ];
    }
}
