<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Carbon\Carbon;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Events\BeamCreated;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateBeamData;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\GraphQL\Types\Scalars\Traits\HasIntegerRanges;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class CreateBeamTest extends TestCaseGraphQL
{
    use CreateBeamData;
    use SeedBeamData;
    use HasIntegerRanges;

    /**
     * The graphql method.
     */
    protected string $method = 'CreateBeam';

    /**
     * Test creating beam with transfer token.
     */
    public function test_it_can_create_beam_with_transfer_token(): void
    {
        $this->genericTestCreateBeam(BeamType::TRANSFER_TOKEN, 1);
    }

    /**
     * Test creating beam with file upload.
     */
    public function test_it_can_create_beam_with_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', "1\n2..10");
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(BeamType::MINT_ON_DEMAND),
            ['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::MINT_ON_DEMAND->name]]]
        ));
        $this->assertNotEmpty($response);

        Event::assertDispatched(BeamCreated::class);
        $this->assertEquals(10, Cache::get(BeamService::key($response)));

        $file = UploadedFile::fake()->createWithContent('tokens.txt', "{$this->token->token_chain_id}\n{$this->token->token_chain_id}..{$this->token->token_chain_id}");
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(BeamType::MINT_ON_DEMAND),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ));
        $this->assertNotEmpty($response);

        Event::assertDispatched(BeamCreated::class);
        $this->assertEquals(1, Cache::get(BeamService::key($response)));
    }

    /**
     * Test creating beam with mint on demand.
     */
    public function test_it_can_create_beam_with_mint_on_demand(): void
    {
        $this->genericTestCreateBeam(BeamType::MINT_ON_DEMAND, random_int(1, 20));
    }

    /**
     * Test creating beam with mint on demand.
     */
    public function test_it_can_create_beam_with_single_use_code(): void
    {
        $this->genericTestCreateBeam(BeamType::MINT_ON_DEMAND, random_int(1, 20), [], [
            ['flag' => 'SINGLE_USE'],
        ]);
    }

    /**
     * Test creating beam with attribute mint on demand.
     */
    public function test_it_can_create_beam_with_attribute_mint_on_demand(): void
    {
        $this->genericTestCreateBeam(
            BeamType::MINT_ON_DEMAND,
            random_int(1, 20),
            [['key' => 'key1', 'value'=> 'value1'], ['key' => 'key2', 'value'=> 'value2']]
        );
    }

    /**
     * Test creating beam with file upload.
     */
    public function test_it_will_fail_to_create_beam_with_invalid_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1');
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamCreated::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1..10');
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => [['tokenIdDataUpload' => $file]]]
        ), true);
        $this->assertArraySubset([
            'tokens.0.tokenIdDataUpload' => ['The tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(BeamCreated::class);
    }

    /**
     * Test creating beam token exist in beam.
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
     * Test creating beam with max length attribute mint on demand.
     */
    public function test_it_will_fail_with_max_length_attribute_mint_on_demand(): void
    {
        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(
                BeamType::MINT_ON_DEMAND,
                random_int(1, 20),
                [['key' => Str::random(256), 'value'=> Str::random(1001)]]
            ),
            true
        );

        $this->assertArraySubset([
            'tokens.0.attributes.0.key' => ['The tokens.0.attributes.0.key field must not be greater than 255 characters.'],
            'tokens.0.attributes.0.value' => ['The tokens.0.attributes.0.value field must not be greater than 1000 characters.'],
        ], $response['error']);
    }

    /**
     * Test creating beam with empty params.
     */
    public function test_it_will_fail_with_empty_params(): void
    {
        $response = $this->graphql($this->method, [], true);
        $this->assertArraySubset([
            ['message' => 'Variable "$name" of required type "String!" was not provided.'],
            ['message' => 'Variable "$description" of required type "String!" was not provided.'],
            ['message' => 'Variable "$image" of required type "String!" was not provided.'],
            ['message' => 'Variable "$start" of required type "DateTime!" was not provided.'],
            ['message' => 'Variable "$end" of required type "DateTime!" was not provided.'],
            ['message' => 'Variable "$collectionId" of required type "BigInt!" was not provided.'],
            ['message' => 'Variable "$tokens" of required type "[ClaimToken!]!" was not provided.'],
        ], $response['errors']);
    }

    /**
     * Test updating beam with max character limit.
     */
    public function test_it_will_fail_with_max_character_limit(): void
    {
        $text = fake()->text(1500);
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            [
                'name' => $text,
                'description' => $text,
                'image' => fake()->url() . '/' . urlencode($text),
            ]
        ), true);

        $this->assertArraySubset([
            'name' => ['The name field must not be greater than 255 characters.'],
            'description' => ['The description field must not be greater than 1024 characters.'],
            'image' => ['The image field must not be greater than 1024 characters.'],
        ], $response['error']);
    }

    /**
     * Test updating beam with empty tokenIds.
     */
    public function test_it_will_fail_with_empty_token_ids(): void
    {
        $response = $this->graphql($this->method, array_merge(
            $this->generateBeamData(),
            ['tokens' => []]
        ), true);

        $this->assertArraySubset(['tokens' => ['The tokens field must have at least 1 items.']], $response['error']);
    }

    /**
     * Test creating beam with invalid parameters.
     */
    public function test_it_will_fail_with_invalid_parameters(): void
    {
        $data = $this->generateBeamData();
        $response = $this->graphql(
            $this->method,
            array_merge($data, ['image' => 'invalid image url']),
            true
        );
        $this->assertArraySubset(['image' => ['The image field must be a valid URL.']], $response['error']);


        $now = Carbon::now();
        $response = $this->graphql(
            $this->method,
            array_merge($data, ['start' => $now->toDateTimeString(), 'end' => $now->subDay()->toDateTimeString()]),
            true
        );
        $this->assertArraySubset([
            'start' => ['The start field must be a date before end.'],
            'end' => ['The end field must be a date after start.'],
        ], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['collectionId' => '1']),
            true
        );
        $this->assertArraySubset(['collectionId' => ['The selected collection id is invalid.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1']]]),
            true
        );
        $this->assertArraySubset(['tokens.0.tokenIds' => ['The tokens.0.tokenIds does not exist in the specified collection.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1..10']]]),
            true
        );
        $this->assertArraySubset(['tokens.0.tokenIds' => ['The tokens.0.tokenIds does not exist in the specified collection.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1'], ['tokenIds' => '1']]]),
            true
        );
        $this->assertArraySubset(['tokens' => ['There are some duplicate token IDs supplied in the data.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1'], ['tokenIds' => '1..10']]]),
            true
        );
        $this->assertArraySubset(['tokens' => ['There are some duplicate token IDs supplied in the data.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1..10'], ['tokenIds' => '1']]]),
            true
        );
        $this->assertArraySubset(['tokens' => ['There are some duplicate token IDs supplied in the data.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            array_merge($data, ['tokens' => [['tokenIds' => '1..10'], ['tokenIds' => '5..10']]]),
            true
        );
        $this->assertArraySubset(['tokens' => ['There are some duplicate token IDs supplied in the data.']], $response['error']);
    }

    /**
     * Test creating beam with invalid ownership.
     */
    public function test_it_will_fail_with_invalid_ownership(): void
    {
        $this->prepareCollectionData(resolve(SubstrateProvider::class)->public_key());
        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(),
            true
        );
        $this->assertArraySubset(['collectionId' => ['The collection id provided is not owned by you and you are not currently approved to use it.']], $response['error']);
    }

    /**
     * Test creating beam with invalid token collection.
     */
    public function test_it_will_fail_with_invalid_token_collection(): void
    {
        $tokenId = $this->token->token_chain_id;
        $this->prepareCollectionData();

        $response = $this->graphql(
            $this->method,
            array_merge($this->generateBeamData(), ['tokens' => [['tokenIds' => [(string) $tokenId]]]]),
            true
        );
        $this->assertArraySubset(['tokens.0.tokenIds' => ['The tokens.0.tokenIds does not exist in the specified collection.']], $response['error']);
    }

    /**
     * Test creating beam with invalid claimQuantity.
     */
    public function test_it_will_fail_with_invalid_claim_quantity(): void
    {
        $this->prepareCollectionData();
        $this->collection->update(['max_token_count' => 0]);
        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(BeamType::MINT_ON_DEMAND, 10),
            true
        );
        $this->assertArraySubset(['tokens.0.claimQuantity' => ['The token count exceeded the maximum limit of 0 for this collection.']], $response['error']);

        $response = $this->graphql(
            $this->method,
            $data = array_merge(
                $this->generateBeamData(BeamType::MINT_ON_DEMAND, 1),
                ['tokens' => [['tokenIds' => ['1'], 'type' => BeamType::MINT_ON_DEMAND->name]]]
            ),
            true
        );
        $this->assertNotEmpty($response);

        $response = $this->graphql($this->method, $data, true);
        $this->assertArraySubset(['tokens.0.claimQuantity' => ['The token count exceeded the maximum limit of 0 for this collection.']], $response['error']);
    }

    /**
     * Test creating beam with invalid tokenQuantityPerClaim.
     */
    public function test_it_will_fail_with_invalid_token_quantity_per_claim(): void
    {
        $this->prepareCollectionData();
        $this->collection->update(['max_token_supply' => 0]);
        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(BeamType::MINT_ON_DEMAND, 10),
            true
        );
        $this->assertArraySubset(
            ['tokens.0.tokenQuantityPerClaim' => ['The tokens.0.tokenQuantityPerClaim exceeded the maximum supply limit of 0 for unique tokens for this collection.']],
            $response['error']
        );

        $response = $this->graphql(
            $this->method,
            $this->generateBeamData(BeamType::TRANSFER_TOKEN, 1),
            true
        );
        $this->assertNotEmpty($response);
        $this->assertArraySubset(
            ['tokens.0.tokenQuantityPerClaim' => ['The tokens.0.tokenQuantityPerClaim exceeded the maximum supply limit of 0 for unique tokens for this collection.']],
            $response['error']
        );
    }

    /**
     * Generic test for create beam.
     */
    protected function genericTestCreateBeam(BeamType $type = BeamType::MINT_ON_DEMAND, int $count = 1, array $attributes = [], array $singleUse = []): void
    {
        $this->truncateBeamTables();

        $response = $this->graphql($this->method, $data = $this->generateBeamData($type, $count, $attributes, $singleUse));

        $this->assertNotEmpty($response);

        Event::assertDispatched(BeamCreated::class);
        $tokenIds = $this->expandRanges(array_column($data['tokens'], 'tokenIds')[0]);
        $this->assertEquals(count($tokenIds) * $count, Cache::get(BeamService::key($response)));
    }
}
