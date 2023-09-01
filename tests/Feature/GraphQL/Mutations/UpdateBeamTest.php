<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Carbon\Carbon;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Events\BeamUpdated;
use Enjin\Platform\Beam\Events\TokensAdded;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Enjin\Platform\GraphQL\Types\Scalars\Traits\HasIntegerRanges;
use Enjin\Platform\Models\Laravel\Collection;
use Enjin\Platform\Models\Laravel\Token;
use Enjin\Platform\Support\Hex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class UpdateBeamTest extends TestCaseGraphQL
{
    use SeedBeamData;
    use HasIntegerRanges;
    use IntegerRange;

    /**
     * The graphql method.
     */
    protected string $method = 'UpdateBeam';

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam(10);
    }

    /**
     * Test updating beam.
     */
    public function test_it_can_update_beam(): void
    {
        Event::fake();
        $response = $this->graphql($this->method, $updates = $this->generateBeamData(BeamType::MINT_ON_DEMAND));
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);

        $this->beam->refresh();
        $this->assertEquals(
            $expected = Arr::except($updates, ['tokens']),
            $this->beam->only(array_keys($expected))
        );

        $totalClaims = collect($updates['tokens'])->sum(function ($token) {
            return collect($token['tokenIds'])->reduce(function ($val, $tokenId) use ($token) {
                $range = $this->integerRange($tokenId);

                return $val + (
                    $range === false
                    ? $token['claimQuantity']
                    : (($range[1] - $range[0]) + 1) * $token['claimQuantity']
                );
            }, 10);
        });
        $this->assertEquals($totalClaims, Cache::get(BeamService::key($this->beam->code)));
    }

    /**
     * Test updating beam with attributes.
     */
    public function test_it_can_update_beam_with_attributes(): void
    {
        Event::fake();
        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(
                BeamType::MINT_ON_DEMAND,
                1,
                [
                    ['key' => 'test', 'value' => 'test'],
                    ['key' => 'test2', 'value' => 'test2'],
                ]
            )
        );
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);
    }

    /**
     * Test creating update with file upload.
     */
    public function test_it_can_update_beam_with_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', "1\n2..10");
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(BeamType::MINT_ON_DEMAND),
            ['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::MINT_ON_DEMAND->name]]]
        ));
        $this->assertNotEmpty($response);
        Event::assertDispatched(BeamUpdated::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', "{$this->token->token_chain_id}\n{$this->token->token_chain_id}..{$this->token->token_chain_id}");
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(BeamType::MINT_ON_DEMAND),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ));
        $this->assertNotEmpty($response);
        Event::assertDispatched(BeamUpdated::class);
    }

    /**
     * Test updating beam token exist in beam.
     */
    public function test_it_will_fail_with_token_exist_in_beam(): void
    {
        $this->collection->update(['max_token_supply' => 1]);
        $this->seedBeam(1, false, BeamType::TRANSFER_TOKEN);
        $claim = $this->claims->first();
        $claim->forceFill(['token_chain_id' => $this->token->token_chain_id])->save();
        $response = $this->graphql(
            $this->method,
            array_merge(
                $data = $this->generateBeamData(BeamType::TRANSFER_TOKEN),
                ['tokens' => [['tokenIds' => [$claim->token_chain_id], 'type' => BeamType::TRANSFER_TOKEN->name]]]
            ),
            true
        );
        $this->assertArraySubset([
            'tokens.0.tokenIds' => ['The tokens.0.tokenIds already exist in beam.'],
        ], $response['error']);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', $this->token->token_chain_id);
        $response = $this->graphql(
            $this->method,
            array_merge(
                $data,
                ['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::TRANSFER_TOKEN->name]]]
            ),
            true
        );
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload already exist in beam.'],
        ], $response['error']);
    }

    /**
     * Test updating beam with file upload.
     */
    public function test_it_will_fail_to_create_beam_with_invalid_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', $this->token->token_chain_id);
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::MINT_ON_DEMAND->name]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload exists in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamUpdated::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', "{$this->token->token_chain_id}..{$this->token->token_chain_id}");
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::MINT_ON_DEMAND->name]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload exists in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamUpdated::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1');
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamUpdated::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1..10');
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamUpdated::class);
    }

    /**
     * Test updating beam with empty parameters.
     */
    public function test_it_will_fail_with_empty_parameters(): void
    {
        $response = $this->graphql($this->method, [], true);

        $this->assertEquals('Variable "$code" of required type "String!" was not provided.', $response['error']);
    }

    /**
     * Test updating beam with existing tokens in beam.
     */
    public function test_it_will_fail_existing_tokens(): void
    {
        $token = (new Token([
            'collection_id' => $this->collection->id,
            'token_chain_id' => (string) fake()->unique()->numberBetween(2000),
            'supply' => (string) $supply = 1,
            'cap' => TokenMintCapType::SINGLE_MINT->name,
            'cap_supply' => null,
            'is_frozen' => false,
            'unit_price' => (string) $unitPrice = fake()->numberBetween(1 / $supply * 10 ** 17),
            'mint_deposit' => (string) ($unitPrice * $supply),
            'minimum_balance' => '1',
            'attribute_count' => '0',
        ]));
        $token->save();

        $updates = array_merge(
            $this->generateBeamData(BeamType::MINT_ON_DEMAND),
            ['tokens' => [['tokenIds' => [$token->token_chain_id]]]]
        );
        $this->assertTrue($this->graphql($this->method, $updates));

        $response = $this->graphql($this->method, $updates, true);
        $this->assertArraySubset(
            ['tokens.0.tokenIds' => ['The tokens.0.tokenIds already exist in beam.']],
            $response['error']
        );

        $updates = array_merge(
            $updates,
            ['tokens' => [['tokenIds' => [$token->token_chain_id . '..' . $token->token_chain_id]]]]
        );
        $response = $this->graphql($this->method, $updates, true);
        $this->assertArraySubset(
            ['tokens.0.tokenIds' => ['The tokens.0.tokenIds already exist in beam.']],
            $response['error']
        );


        $updates = array_merge(
            $updates,
            ['tokens' => [['tokenIds' => [$token->token_chain_id . '..' . $token->token_chain_id], 'type' => BeamType::MINT_ON_DEMAND->name]]]
        );
        $response = $this->graphql($this->method, $updates, true);
        $this->assertArraySubset(
            ['tokens.0.tokenIds' => ['The tokens.0.tokenIds exists in the specified collection.']],
            $response['error']
        );

        $collection = Collection::create([
            'collection_chain_id' => (string) fake()->unique()->numberBetween(2000),
            'owner_wallet_id' => $this->wallet->id,
            'max_token_count' => '1',
            'max_token_supply' => '1',
            'force_single_mint' => true,
            'is_frozen' => false,
            'token_count' => '0',
            'attribute_count' => '0',
            'total_deposit' => '0',
            'network' => 'developer',
        ]);

        $create = [
            'name' => fake()->name(),
            'description' => fake()->word(),
            'image' => fake()->url(),
            'start' => Carbon::now()->toDateTimeString(),
            'end' => Carbon::now()->addDays(random_int(1, 1000))->toDateTimeString(),
            'collectionId' => $collection->collection_chain_id,
            'tokens' => [[
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['0'],
                'tokenQuantityPerClaim' => 1,
                'claimQuantity' => 1,
                'attributes' => null,
            ]],
        ];
        $this->assertNotEmpty($code = $this->graphql('CreateBeam', $create));

        $updates = array_merge(
            Arr::only($create, ['tokens']),
            ['code' => $code]
        );
        $response = $this->graphql($this->method, $updates, true);
        $this->assertArraySubset(
            ['tokens.0.tokenIds' => ['The tokens.0.tokenIds already exist in beam.']],
            $response['error']
        );
    }

    /**
     * Test updating beam with max character limit.
     */
    public function test_it_will_fail_with_max_character_limit(): void
    {
        $text = fake()->text(1500);
        $response = $this->graphql($this->method, [
            'code' => $text,
            'name' => $text,
            'description' => $text,
            'image' => fake()->url() . '/' . urlencode($text),
        ], true);

        $this->assertArraySubset([
            'code' => ['The code field must not be greater than 1024 characters.'],
            'name' => ['The name field must not be greater than 255 characters.'],
            'description' => ['The description field must not be greater than 1024 characters.'],
            'image' => ['The image field must not be greater than 1024 characters.'],
        ], $response['error']);
    }

    /**
     * Test updating beam with invalid parameters.
     */
    public function test_it_will_fail_with_invalid_parameters(): void
    {
        $data = $this->generateBeamData();
        $response = $this->graphql(
            $this->method,
            array_merge($data, ['code' => fake()->text(10)]),
            true
        );
        $this->assertArraySubset(['code' => ['The selected code is invalid.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['image' => 'invalid image url']),
            true
        );
        $this->assertArraySubset(['image' => ['The image field must be a valid URL.']], $response['error']);
    }

    /**
     * Test updating beam with invalid dates.
     */
    public function test_it_will_fail_with_invalid_dates(): void
    {
        $now = Carbon::now();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'start' => $now->toDateTimeString(),
                'end' => $now->clone()->subDay()->toDateTimeString(),
            ],
            true
        );
        $this->assertArraySubset(['start' => ['The start must be a date before end.'], 'end' => ['The end must be a date after start.']], $response['error']);

        $start = Carbon::parse($this->beam->start);
        $end = Carbon::parse($this->beam->end);

        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'start' => $end->clone()->addDay()->toDateTimeString()],
            true
        );
        $this->assertArraySubset(['start' => ["The start must be a date less than {$end->toDateTimeString()}."]], $response['error']);

        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'end' => $start->clone()->subDay()->toDateTimeString()],
            true
        );
        $this->assertArraySubset(['end' => ["The end must be a date greater than {$start->toDateTimeString()}."]], $response['error']);

        $this->beam->fill(['start' => $now->clone()->subDay()->toDateTimeString()])->save();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'start' => $now->clone()->toDateTimeString(),
                'end' => $end->clone()->addDay()->toDateTimeString(),
            ],
            true
        );
        $this->assertArraySubset(['start' => ['The start date of this beam has passed, it can no longer be modified.']], $response['error']);
    }

    /**
     * Test updating beam flags.
     */
    public function test_it_can_update_beam_flags(): void
    {
        $this->assertTrue(!$this->beam->hasFlag(BeamFlag::PAUSED));

        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'flags' => [['flag' => BeamFlag::PAUSED->name, 'enabled' => true]]]
        );

        $this->assertTrue($response);

        $this->beam->refresh();

        $this->assertTrue($this->beam->hasFlag(BeamFlag::PAUSED));
    }

    /**
     * Test updating with empty flags.
     */
    public function test_it_can_update_beam_with_empty_flags(): void
    {
        $this->assertTrue(!$this->beam->hasFlag(BeamFlag::PAUSED));

        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'flags' => [['flag' => BeamFlag::PAUSED->name, 'enabled' => true]]]
        );

        $this->assertTrue($response);
        $this->beam->refresh();
        $this->assertTrue($this->beam->hasFlag(BeamFlag::PAUSED));

        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'flags' => [['flag' => BeamFlag::PAUSED->name, 'enabled' => false]]]
        );

        $this->assertTrue($response);
        $this->beam->refresh();
        $this->assertTrue(!$this->beam->hasFlag(BeamFlag::PAUSED));
    }

    /**
     * Test updating beam with invalid flags.
     */
    public function test_it_will_fail_with_invalid_flags(): void
    {
        $response = $this->graphql(
            $this->method,
            ['code' => $this->beam->code, 'flags' => ['invalid flag']],
            true
        );

        $this->assertEquals('Variable "$flags" got invalid value "invalid flag" at "flags[0]"; Expected type "BeamFlagInputType" to be an object.', $response['error']);
    }

    /**
     * Test updating beam with invalid tokens.
     */
    public function test_it_will_fail_with_invalid_tokens(): void
    {
        $updates = $this->generateBeamData(BeamType::MINT_ON_DEMAND);

        $response = $this->graphql(
            $this->method,
            array_merge($updates, ['tokens' => [['tokenIds' => [Hex::MAX_UINT256]]]]),
            true
        );
        $this->assertArraySubset(
            ['tokens.0.tokenIds' => ['The tokens.0.tokenIds is too large, the maximum value it can be is 340282366920938463463374607431768211455.']],
            $response['error']
        );

        $response = $this->graphql(
            $this->method,
            array_merge($updates, ['tokens' => [['tokenIds' => ['5..1']]]]),
            true
        );
        $this->assertEquals(
            'Variable "$tokens" got invalid value "5..1" at "tokens[0].tokenIds[0]"; Cannot represent following value as integer range: "5..1"',
            $response['error']
        );
    }

    /**
     * Generate beam data.
     */
    protected function generateBeamData(BeamType $type = BeamType::TRANSFER_TOKEN, int $count = 1, array $attributes = [], array $singleUse = []): array
    {
        return [
            'code' => $this->beam->code,
            'name' => 'Updated',
            'description' => 'Updated',
            'image' => fake()->url(),
            'start' => Carbon::now()->addDays(10)->toDateTimeString(),
            'end' => Carbon::now()->addDays(20)->toDateTimeString(),
            'tokens' => [[
                'type' => $type->name,
                'tokenIds' => BeamType::TRANSFER_TOKEN == $type
                    ? [(string) $this->token->token_chain_id]
                    : [(string) fake()->unique()->numberBetween(100, 10000), fake()->unique()->numberBetween(0, 10) . '..' . fake()->unique()->numberBetween(11, 20)],
                'tokenQuantityPerClaim' => random_int(1, $count),
                'claimQuantity' => $count,
                'attributes' => $attributes ?: null,
            ]],
        ];
    }
}
