<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Carbon\Carbon;
use Crypto\sr25519;
use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Events\BeamClaimPending;
use Enjin\Platform\Beam\Jobs\ClaimBeam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\PassesClaimConditions;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateBeamData;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Enums\Substrate\CryptoSignatureType;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Enjin\Platform\Support\Account;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class ClaimBeamTest extends TestCaseGraphQL
{
    use SeedBeamData;
    use CreateBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'ClaimBeam';

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam();
    }

    /**
     * Test claiming beam with updated single use codes.
     */
    public function test_it_can_claim_updated_single_use_codes(): void
    {
        $data = $this->generateBeamData(BeamType::MINT_ON_DEMAND, 5);
        $code = $this->graphql('CreateBeam', array_merge(
            $data,
            ['tokens' => [
                ['tokenIds' => '1', 'type' => BeamType::MINT_ON_DEMAND->name],
                ['tokenIds' => '2', 'type' => BeamType::MINT_ON_DEMAND->name],
            ]]
        ));
        $this->assertNotEmpty($code);
        $this->genericClaimTest(CryptoSignatureType::ED25519, $code);

        $response = $this->graphql('UpdateBeam', [
            'code' => $code,
            'flags' => [['flag' => BeamFlag::SINGLE_USE->name]],
        ]);
        $this->assertTrue($response);

        $response = $this->graphql('GetSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($response['totalCount']);


        Queue::fake();
        $response = $this->graphql($this->method, [
            'code' => Arr::get($response, 'edges.0.node.code'),
            'account' => resolve(SubstrateProvider::class)->public_key(),
            'signature' => '',
        ]);
        $this->assertTrue($response);

        Queue::assertPushed(ClaimBeam::class);
        Event::assertDispatched(BeamClaimPending::class);
    }

    /**
     * Test claiming beam with sr25519 for single use codes.
     */
    public function test_it_can_claim_beam_with_sr25519_single_use_codes(): void
    {
        $code = $this->graphql('CreateBeam', $this->generateBeamData(
            BeamType::MINT_ON_DEMAND,
            1,
            [],
            [['flag' => 'SINGLE_USE']],
        ));
        $response = $this->graphql('GetSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($response['totalCount']);

        $this->genericClaimTest(CryptoSignatureType::SR25519, Arr::get($response, 'edges.0.node.code'));
    }

    /**
     * Test claiming beam with ed25519.
     */
    public function test_it_can_claim_beam_with_ed25519(): void
    {
        $this->genericClaimTest(CryptoSignatureType::ED25519);
    }

    /**
     * Test it can remove a condition from the rule.
     */
    public function test_it_can_remove_a_condition_from_the_rule(): void
    {
        PassesClaimConditions::addConditionalFunctions([
            function ($attribute, $code, $singleUse, $data) {
                return CryptoSignatureType::ED25519->name == $data['cryptoSignatureType'];
            },
            function ($attribute, $code, $singleUse, $data) {
                return 'code' == $attribute;
            },
        ]);
        $this->assertCount(2, PassesClaimConditions::getConditionalFunctions());

        PassesClaimConditions::removeConditionalFunctions(
            function ($attribute, $code, $singleUse, $data) {
                return 'code' == $attribute;
            }
        );
        $this->assertCount(1, PassesClaimConditions::getConditionalFunctions());

        $this->genericClaimTest(CryptoSignatureType::ED25519);

        PassesClaimConditions::clearConditionalFunctions();
        $this->assertEmpty(PassesClaimConditions::getConditionalFunctions());
    }

    /**
     * Test claiming beam with a single passing condition.
     */
    public function test_it_can_claim_beam_with_single_condition_that_passes(): void
    {
        PassesClaimConditions::addConditionalFunctions(function ($attribute, $code, $singleUse, $data) {
            return CryptoSignatureType::ED25519->name == $data['cryptoSignatureType'];
        });
        $this->assertNotEmpty(PassesClaimConditions::getConditionalFunctions());

        $this->genericClaimTest(CryptoSignatureType::ED25519);

        PassesClaimConditions::clearConditionalFunctions();
        $this->assertEmpty(PassesClaimConditions::getConditionalFunctions());
    }

    /**
     * Test claiming beam with multiple passing conditions.
     */
    public function test_it_can_claim_beam_with_multiple_conditions_that_pass(): void
    {
        PassesClaimConditions::addConditionalFunctions([
            function ($attribute, $code, $singleUse, $data) {
                return CryptoSignatureType::ED25519->name == $data['cryptoSignatureType'];
            },
            function ($attribute, $code, $singleUse, $data) {
                return 'code' == $attribute;
            },
        ]);
        $this->assertNotEmpty(PassesClaimConditions::getConditionalFunctions());

        $this->genericClaimTest(CryptoSignatureType::ED25519);

        PassesClaimConditions::clearConditionalFunctions();
        $this->assertEmpty(PassesClaimConditions::getConditionalFunctions());
    }

    /**
     * Test claiming beam with failing conditions.
     */
    public function test_it_cannot_claim_beam_with_single_condition_that_fails(): void
    {
        PassesClaimConditions::addConditionalFunctions(function ($attribute, $code, $singleUse, $data) {
            return CryptoSignatureType::SR25519->name == $data['cryptoSignatureType'] ? true : 'Signature is not SR25519.';
        });
        $this->assertNotEmpty(PassesClaimConditions::getConditionalFunctions());

        [$keypair, $publicKey, $privateKey] = $this->getKeyPair(CryptoSignatureType::ED25519);

        $response = $this->graphql('GetBeam', [
            'code' => $this->beam->code,
            'account' => $publicKey,
        ]);
        $this->assertNotEmpty($response['message']);

        $message = $response['message']['message'];
        $signature = $this->signMessage(CryptoSignatureType::ED25519, $keypair, $message, $privateKey);

        Queue::fake();

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publicKey,
            'signature' => $signature,
            'cryptoSignatureType' => CryptoSignatureType::ED25519->name,
        ], true);

        $this->assertNotEmpty($response);

        $this->assertArraySubset(['code' => ['Signature is not SR25519.']], $response['error']);

        PassesClaimConditions::clearConditionalFunctions();
        $this->assertEmpty(PassesClaimConditions::getConditionalFunctions());
    }

    /**
     * Test claiming beam with multiple failing conditions.
     */
    public function test_it_cannot_claim_beam_with_multiple_conditions_that_fail(): void
    {
        $functions = collect([
            function ($attribute, $code, $singleUse, $data) {
                return 'code' == $data[$attribute];
            },
            function ($attribute, $code, $singleUse, $data) {
                return CryptoSignatureType::SR25519->name == $data['cryptoSignatureType'] ? true : 'Signature is not SR25519.';
            },
        ]);

        PassesClaimConditions::addConditionalFunctions($functions);
        $this->assertNotEmpty(PassesClaimConditions::getConditionalFunctions());

        [$keypair, $publicKey, $privateKey] = $this->getKeyPair(CryptoSignatureType::ED25519);

        $response = $this->graphql('GetBeam', [
            'code' => $this->beam->code,
            'account' => $publicKey,
        ]);
        $this->assertNotEmpty($response['message']);

        $message = $response['message']['message'];
        $signature = $this->signMessage(CryptoSignatureType::ED25519, $keypair, $message, $privateKey);

        Queue::fake();

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publicKey,
            'signature' => $signature,
            'cryptoSignatureType' => CryptoSignatureType::ED25519->name,
        ], true);

        $this->assertNotEmpty($response);

        $this->assertArraySubset(['code' => [
            'A condition to claim has not been met.',
            'Signature is not SR25519.',
        ]], $response['error']);

        PassesClaimConditions::clearConditionalFunctions();
        $this->assertEmpty(PassesClaimConditions::getConditionalFunctions());
    }

    /**
     * Test claiming beam with expired date.
     */
    public function test_it_will_fail_with_expired_date(): void
    {
        [$keypair, $publicKey, $privateKey] = $this->getKeyPair(CryptoSignatureType::ED25519);
        $this->beam->update(['end' => Carbon::now()->subDays(1)->startOfDay()]);
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publicKey,
            'signature' => fake()->text(10),
        ], true);

        $this->assertArraySubset(['code' => ['The beam has expired.']], $response['error']);
    }

    /**
     * Test claiming beam with empty parameter.
     */
    public function test_it_will_fail_with_empty_params(): void
    {
        $response = $this->graphql($this->method, [], true);

        $this->assertArraySubset([
            ['message' => 'Variable "$code" of required type "String!" was not provided.'],
            ['message' => 'Variable "$account" of required type "String!" was not provided.'],
            ['message' => 'Variable "$signature" of required type "String!" was not provided.'],
        ], $response['errors']);
    }

    /**
     * Test claiming beam without scanning.
     */
    public function test_it_will_fail_with_without_scanning(): void
    {
        [$keypair, $publicKey, $privateKey] = $this->getKeyPair();

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publicKey,
            'signature' => (new sr25519())->Sign($keypair, fake()->text(10)),
        ], true);

        $this->assertArraySubset(['signature' => ['Beam scan record is not found.']], $response['error']);
    }

    /**
     * Test claiming beam with invalid parameters.
     */
    public function test_it_will_fail_with_invalid_parameters(): void
    {
        $response = $this->graphql($this->method, [
            'code' => $randString = fake()->text(10),
            'account' => $randString,
            'signature' => $randString,
        ], true);

        $this->assertArraySubset([
            'code' => ['The selected code is invalid.'],
            'account' => ['The account is not a valid substrate account.'],
            'signature' => ['The account is not a valid substrate account.'],
        ], $response['error']);

        $this->assertArraySubset(['account' => ['The account is not a valid substrate account.']], $response['error']);
    }

    /**
     * Test claiming beam with invalid account parameter.
     */
    public function test_it_will_fail_with_invalid_account_parameter(): void
    {
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => Account::daemonPublicKey(),
            'signature' => fake()->text(10),
        ], true);

        $this->assertArraySubset(['account' => ['The account should not be the owner of the collection.']], $response['error']);
    }

    /**
     * Test claiming a paused beam.
     */
    public function test_it_will_fail_with_paused_beam(CryptoSignatureType $type = CryptoSignatureType::ED25519): void
    {
        [$keypair, $publicKey, $privateKey] = $this->getKeyPair($type);

        $response = $this->graphql('GetBeam', [
            'code' => $this->beam->code,
            'account' => $publicKey,
        ]);
        $this->assertNotEmpty($response['message']);

        // Pause the beam
        $this->graphql('UpdateBeam', [
            'code' => $this->beam->code,
            'flags' => [[
                'flag' => BeamFlag::PAUSED->name,
                'enabled' => true,
            ]],
        ]);

        $message = $response['message']['message'];
        $signature = $this->signMessage($type, $keypair, $message, $privateKey);

        Queue::fake();

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publicKey,
            'signature' => $signature,
            'cryptoSignatureType' => $type->name,
        ], true);

        $this->assertNotEmpty($response);
        $this->assertArraySubset(['code' => ['The beam is paused.']], $response['error']);
    }

    /**
     * Test claiming beam with invalid parameters (updated single use codes).
     */
    public function test_it_will_fail_with_invalid_params_updated_single_use_codes(): void
    {
        $code = $this->graphql('CreateBeam', $this->generateBeamData(
            BeamType::MINT_ON_DEMAND,
            5,
        ));
        $this->assertNotEmpty($code);

        [$keypair, $publicKey, $privateKey] = $this->getKeyPair($type = CryptoSignatureType::ED25519);
        $claim = BeamClaim::hasCode($code)->first();

        $response = $this->graphql('GetBeam', [
            'code' => $claim->singleUseCode,
            'account' => $publicKey,
        ], true);
        $this->assertArraySubset(['code' => ['The selected code is invalid.']], $response['error']);

        $response = $this->graphql($this->method, [
            'code' => $claim->singleUseCode,
            'account' => $publicKey,
            'cryptoSignatureType' => $type->name,
            'signature' => '',
        ], true);
        $this->assertArraySubset(['code' => ['The selected code is invalid.']], $response['error']);
    }

    /**
     * Sign a message.
     */
    public function signMessage(CryptoSignatureType $type, mixed $keypair, string $message, string $privateKey): string
    {
        if (CryptoSignatureType::SR25519 == $type) {
            $signature = (new sr25519())->Sign($keypair, $message);
        } else {
            $message = HexConverter::stringToHex($message);
            $signature = HexConverter::prefix(bin2hex(sodium_crypto_sign_detached(sodium_hex2bin($message), $privateKey)));
        }

        return $signature;
    }

    /**
     * Get keypair.
     */
    protected function getKeyPair(CryptoSignatureType $type = CryptoSignatureType::SR25519): array
    {
        if (CryptoSignatureType::SR25519 == $type) {
            $sr = new sr25519();
            $keypair = $sr->InitKeyPair(bin2hex(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())));
            $public = HexConverter::prefix($keypair->publicKey);
            $private = ''; // not accessible
        } else {
            $keypair = sodium_crypto_sign_keypair();
            $public = HexConverter::prefix(bin2hex(sodium_crypto_sign_publickey($keypair)));
            $private = sodium_crypto_sign_secretkey($keypair);
        }

        return [$keypair, SS58Address::encode($public), $private];
    }

    /**
     * Generate test for claiming beam.
     */
    protected function genericClaimTest(CryptoSignatureType $type = CryptoSignatureType::SR25519, string $singleUseCode = ''): void
    {
        [$keypair, $publicKey, $privateKey] = $this->getKeyPair($type);

        $response = $this->graphql('GetBeam', [
            'code' => $singleUseCode ?: $this->beam->code,
            'account' => $publicKey,
        ]);
        $this->assertNotEmpty($response['message']);
        if (!$singleUseCode) {
            $this->assertEquals(1, $this->beam->scans()->count());
        }

        $message = $response['message']['message'];
        $signature = $this->signMessage($type, $keypair, $message, $privateKey);

        Queue::fake();

        $response = $this->graphql($this->method, [
            'code' => $singleUseCode ?: $this->beam->code,
            'account' => $publicKey,
            'signature' => $signature,
            'cryptoSignatureType' => $type->name,
        ]);

        $this->assertTrue($response);

        Queue::assertPushed(ClaimBeam::class);
        Event::assertDispatched(BeamClaimPending::class);
    }
}
