<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Events\TokensRemoved;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Illuminate\Support\Facades\Event;

class RemoveTokensTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'RemoveTokens';

    /**
     * Test removing tokens.
     */
    public function test_it_can_remove_tokens(): void
    {
        $this->seedBeam(5);

        Event::fake();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => [$this->claims->shift()->token_chain_id],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);


        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => ["{$claim->token_chain_id}..{$claim->token_chain_id}"],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);

        $this->seedBeamPack(3);
        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [
                ['id' => $claim->beam_pack_id, 'tokenIds' => [$claim->token_chain_id]],
            ],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);

        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [
                ['id' => $claim->beam_pack_id, 'tokenIds' => ["{$claim->token_chain_id}..{$claim->token_chain_id}"]],
            ],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);

        $claim = $this->claims->shift();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [
                ['id' => $claim->beam_pack_id],
            ],
        ]);
        $this->assertTrue($response);
        Event::assertDispatched(TokensRemoved::class);
    }

    /**
     * Test invalid parameters.
     */
    public function test_it_will_fail_with_invalid_paramters(): void
    {
        $this->seedBeam(1);
        $response = $this->graphql($this->method, [
            'code' => null,
            'tokenIds' => $this->claims->pluck('token_chain_id')->toArray(),
        ], true);
        $this->assertEquals(
            'Variable "$code" of non-null type "String!" must not be null.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => '',
            'tokenIds' => $this->claims->pluck('token_chain_id')->toArray(),
        ], true);
        $this->assertArrayContainsArray(
            ['code' => ['The code field must have a value.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => null,
        ], true);
        $this->assertEquals(
            ['tokenIds' => ['The token ids field is required.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => [],
        ], true);
        $this->assertArrayContainsArray(
            ['tokenIds' => ['The token ids field is required.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => [''],
        ], true);
        $this->assertEquals(
            'Variable "$tokenIds" got invalid value (empty string) at "tokenIds[0]"; Cannot represent following value as integer range: (empty string)',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => [null],
        ], true);
        $this->assertEquals(
            'Variable "$tokenIds" got invalid value null at "tokenIds[0]"; Expected non-nullable type "IntegerRangeString!" not to be null.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => ['0'],
        ], true);
        $this->assertArrayContainsArray(
            ['tokenIds.0' => ["The tokenIds.0 doesn't exist in beam."]],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => ['0..5'],
        ], true);
        $this->assertArrayContainsArray(
            ['tokenIds.0' => ["The tokenIds.0 doesn't exist in beam."]],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => ['0', '0'],
        ], true);
        $this->assertArrayContainsArray(
            [
                'tokenIds.0' => ['The tokenIds.0 field has a duplicate value.'],
                'tokenIds.1' => ['The tokenIds.1 field has a duplicate value.'],
            ],
            $response['error']
        );

        $this->seedBeam(1, 1);
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'tokenIds' => $this->claims->pluck('token_chain_id')->toArray(),
        ], true);
        $this->assertArrayContainsArray(
            ['tokenIds.0' => ["The tokenIds.0 doesn't exist in beam."]],
            $response['error']
        );

        $this->seedBeamPack();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [],
        ], true);
        $this->assertArrayContainsArray(
            ['packs' => ['The packs field is required.']],
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['tokens' => null]],
        ], true);
        $this->assertEquals(
            'Variable "$packs" got invalid value {"tokens":null} at "packs[0]"; Field "id" of required type "Int!" was not provided.',
            $response['error']
        );

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'packs' => [['id' => 100000, 'tokenIds' => ['10000']]],
        ], true);
        $this->assertArrayContainsArray(
            [
                'packs.0.id' => ['The packs.0.id doesn\'t exist in beam.'],
                'packs.0.tokenIds.0' => ['The packs.0.tokenIds.0 doesn\'t exist in beam pack.'],
            ],
            $response['error']
        );

    }
}
