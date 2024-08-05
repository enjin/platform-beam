<?php

namespace Enjin\Platform\Beam\Database\Factories;

use Enjin\Platform\Beam\Models\BeamPack;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeamPackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var BeamPack
     */
    protected $model = BeamPack::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'is_claimed' => fake()->boolean(),
            'code' => fake()->text(),
            'nonce' => 1,
        ];
    }
}
