<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Events\TokensAdded;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\GraphQL\Types\Scalars\Traits\HasIntegerRanges;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class AddPackTokensTest extends TestCaseGraphQL
{
    use HasIntegerRanges;
    use IntegerRange;
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'AddTokensBeamPack';

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam(1, false, null, ['is_pack' => true]);
    }

    public function test_it_add_tokens(): void
    {
        Event::fake();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIds' => ['1..5'], 'type' => BeamType::MINT_ON_DEMAND->name]]]],
            ]
        );
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);
    }

    public function test_it_can_add_token_with_attributes(): void
    {
        Event::fake();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [[
                    'tokenIds' => ['1..5'],
                    'type' => BeamType::MINT_ON_DEMAND->name,
                    'attributes' => [
                        ['key' => 'test', 'value' => 'test'],
                        ['key' => 'test2', 'value' => 'test2'],
                    ],
                ]]]],
            ],
        );
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);
    }

    public function test_it_can_update_beam_with_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', "1\n2..10");
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::MINT_ON_DEMAND->name]]]],
            ]
        );
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', "{$this->token->token_chain_id}\n{$this->token->token_chain_id}..{$this->token->token_chain_id}");
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIdDataUpload' => $file]]]],
            ]
        );
        $this->assertTrue($response);
        Event::assertDispatched(TokensAdded::class);
    }

    public function test_it_will_fail_with_token_exist_in_beam(): void
    {
        $this->collection->update(['max_token_supply' => 1]);
        $this->seedBeam(1, false, BeamType::TRANSFER_TOKEN);
        $claim = $this->claims->first();
        $claim->forceFill(['token_chain_id' => $this->token->token_chain_id])->save();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIds' => [$claim->token_chain_id], 'type' => BeamType::TRANSFER_TOKEN->name]]]],
            ],
            true
        );
        $this->assertArraySubset([
            'packs.0.tokens.0.tokenIds' => ['The packs.0.tokens.0.tokenIds already exist in beam.'],
        ], $response['error']);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', $this->token->token_chain_id);
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIdDataUpload' => $file, 'type' => BeamType::TRANSFER_TOKEN->name]]]],
            ],
            true
        );
        $this->assertArraySubset([
            'packs.0.tokens.0.tokenIdDataUpload' => ['The packs.0.tokens.0.tokenIdDataUpload already exist in beam.'],
        ], $response['error']);
    }

    public function test_it_will_fail_to_create_beam_with_invalid_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1');
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIdDataUpload' => $file]]]],
            ],
            true
        );
        $this->assertArraySubset([
            'packs.0.tokens.0.tokenIdDataUpload' => ['The packs.0.tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(TokensAdded::class);

        $file = UploadedFile::fake()->createWithContent('tokens.txt', '1..10');
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['tokens' => [['tokenIdDataUpload' => $file]]]],
            ],
            true
        );
        $this->assertArraySubset([
            'packs.0.tokens.0.tokenIdDataUpload' => ['The packs.0.tokens.0.tokenIdDataUpload does not exist in the specified collection.'],
        ], $response['error']);
        Event::assertNotDispatched(TokensAdded::class);
    }

    /**
     * Test updating beam with invalid parameters.
     */
    public function test_it_will_fail_with_invalid_parameters(): void
    {
        $response = $this->graphql(
            $this->method,
            [
                'code' => fake()->text(),
                'packs' => [],
            ],
            true
        );
        $this->assertArraySubset([
            'code' => ['The selected code is invalid.'],
            'packs' => ['The packs field must have at least 1 items.'],
        ], $response['error']);

        $response = $this->graphql(
            $this->method,
            [
                'code' => null,
                'packs' => null,
            ],
            true
        );
        $this->assertEquals($response['error'], 'Variable "$code" of non-null type "String!" must not be null.');

        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => null,
            ],
            true
        );
        $this->assertEquals($response['error'], 'Variable "$packs" of non-null type "[BeamPackInput!]!" must not be null.');

        $response = $this->graphql($this->method, [
            'code' => Str::random(1500),
            'packs' => [['tokens' => [['tokenIds' => ['1..5'], 'type' => BeamType::MINT_ON_DEMAND->name]]]],
        ], true);
        $this->assertArraySubset(
            ['code' => ['The code field must not be greater than 1024 characters.']],
            $response['error']
        );
    }
}
