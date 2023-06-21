<?php

namespace Enjin\Platform\Beam\Database\Factories;

use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeamScanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var BeamScan
     */
    protected $model = BeamScan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'wallet_public_key' => resolve(SubstrateProvider::class)->public_key(),
            'message' => BeamService::generateSigningRequestMessage(),
            'count' => random_int(1, 10),
        ];
    }
}
