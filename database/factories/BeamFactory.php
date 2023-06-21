<?php

namespace Enjin\Platform\Beam\Database\Factories;

use Carbon\Carbon;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var Beam
     */
    protected $model = Beam::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $date = Carbon::now()->addDay();

        return [
            'code' => bin2hex(openssl_random_pseudo_bytes(16)),
            'name' => fake()->name(),
            'description' => fake()->word(),
            'image' => fake()->url(),
            'start' => $date->toDateTimeString(),
            'end' => $date->addDays(random_int(1, 10000)),
        ];
    }
}
