<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Events\TokensRemoved;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamPack;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Illuminate\Support\Facades\Event;

class RemovePackTokensTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'RemoveTokensBeamPack';

    public function test_it_can_remove_tokens(): void
    {
        $this->seedBeamPack(2);
        Event::fake();
        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $claim->beam_pack_id, 'tokenIds' => [$claim->token_chain_id]]],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);

        $this->assertFalse(
            BeamClaim::where('token_chain_id', $claim->token_chain_id)
                ->where('beam_pack_id', $claim->beam_pack_id)
                ->exists()
        );
    }

    public function test_it_can_remove_token_range(): void
    {
        $this->seedBeamPack(2);
        Event::fake();
        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $claim->beam_pack_id, 'tokenIds' => ["{$claim->token_chain_id}..{$claim->token_chain_id}"]]],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);

        $this->assertFalse(
            BeamClaim::whereBetween('token_chain_id', [$claim->token_chain_id, $claim->token_chain_id])
                ->where('beam_pack_id', $claim->beam_pack_id)
                ->exists()
        );
    }

    public function test_it_cannot_use_on_non_beam_pack(): void
    {
        $this->seedBeamPack();
        $this->beam->fill(['is_pack' => false])->save();
        $response = $this->graphql(
            $this->method,
            [
                'code' => $this->beam->code,
                'packs' => [['id' => $this->claims->first()->id]],
            ],
            true
        );
        $this->assertArraySubset(
            ['code' => ['This mutation is not applicable to non-beam packs.']],
            $response['error']
        );
        $this->beam->fill(['is_pack' => true])->save();
    }

    public function test_it_can_remove_beam_packs(): void
    {
        $this->seedBeamPack(2);
        $packIds = $this->claims->pluck('beam_pack_id')->unique();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => $packIds->map(fn ($id) => ['id' => $id])->toArray(),
        ]);
        $this->assertTrue($response);

        $this->assertFalse(BeamPack::whereIn('id', $packIds)->exists());
    }

    public function test_it_will_fail_with_invalid_paramters(): void
    {
        $this->seedBeamPack(1);
        $response = $this->graphql($this->method, [
            'code' => null,
            'packs' => [[
                'id' => $this->claims->first()->id,
                'tokenIds' => $this->claims->pluck('token_chain_id')->toArray(),
            ]],
        ], true);
        $this->assertEquals(
            'Variable "$code" of non-null type "String!" must not be null.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => '',
            'packs' => [[
                'id' => $this->claims->first()->id,
                'tokenIds' => $this->claims->pluck('token_chain_id')->toArray(),
            ]],
        ], true);
        $this->assertArraySubset(
            ['code' => ['The code field must have a value.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => null,
        ], true);
        $this->assertEquals(
            'Variable "$packs" of non-null type "[RemoveBeamPack!]!" must not be null.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['tokenIds' => $this->claims->pluck('token_chain_id')->toArray()]],
        ], true);
        $this->assertStringContainsString(
            'Field "id" of required type "Int!" was not provided.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $this->claims->first()->beam_pack_id, 'tokenIds' => ['']]],
        ], true);
        $this->assertEquals(
            'Variable "$packs" got invalid value (empty string) at "packs[0].tokenIds[0]"; Cannot represent following value as integer range: (empty string)',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $this->claims->first()->beam_pack_id, 'tokenIds' => [null]]],
        ], true);
        $this->assertEquals(
            'Variable "$packs" got invalid value null at "packs[0].tokenIds[0]"; Expected non-nullable type "IntegerRangeString!" not to be null.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => 10001]],
        ], true);
        $this->assertArraySubset(
            ['packs.0.id' => ['The packs.0.id doesn\'t exist in beam.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $this->claims->first()->beam_pack_id, 'tokenIds' => ['1000001']]],
        ], true);
        $this->assertArraySubset(
            ['packs.0.tokenIds.0' => ['The packs.0.tokenIds.0 doesn\'t exist in beam pack.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $this->claims->first()->beam_pack_id, 'tokenIds' => ['1000001..1000002']]],
        ], true);
        $this->assertArraySubset(
            ['packs.0.tokenIds.0' => ["The packs.0.tokenIds.0 doesn't exist in beam pack."]],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [
                ['id' => $this->claims->first()->beam_pack_id],
                ['id' => $this->claims->first()->beam_pack_id],
            ],
        ], true);
        $this->assertArraySubset(
            [
                'packs.0.id' => ['The packs.0.id field has a duplicate value.'],
                'packs.1.id' => ['The packs.1.id field has a duplicate value.'],
            ],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => $this->claims->first()->beam_pack_id, 'tokenIds' => ['1', '1']]],
        ], true);
        $this->assertArraySubset(
            [
                'packs.0.tokenIds.0' => ['The packs.0.tokenIds.0 field has a duplicate value.'],
                'packs.0.tokenIds.1' => ['The packs.0.tokenIds.1 field has a duplicate value.'],
            ],
            $response['error']
        );
    }
}
