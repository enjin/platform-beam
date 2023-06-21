<?php

namespace Enjin\Platform\Beam\Database\Factories;

use Carbon\Carbon;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeamClaimFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var BeamClaim
     */
    protected $model = BeamClaim::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'wallet_public_key' => resolve(SubstrateProvider::class)->public_key(),
            'claimed_at' => Carbon::now()->toDateTimeString(),
            'token_chain_id' => (string) fake()->unique()->numberBetween(2000),
            'type' => fake()->randomElement(BeamType::caseNamesAsArray()),
        ];
    }
}
