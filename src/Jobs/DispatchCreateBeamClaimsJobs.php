<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class DispatchCreateBeamClaimsJobs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use IntegerRange;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Model $beam,
        protected ?array $tokens
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $beam = $this->beam->load('collection');
        $singleUse = $beam->hasFlag(BeamFlag::SINGLE_USE);
        $tokens = collect($this->tokens);

        $tokens->chunk(1000)
            ->each(function (Collection $chunks) use ($beam, $singleUse) {
                $claims = collect();
                $chunks->each(function ($token) use ($beam, $singleUse, $claims) {
                    collect($token['tokenIds'])->each(function ($tokenId) use ($beam, $singleUse, $claims, $token) {
                        $range = $this->integerRange($tokenId);
                        if (false === $range) {
                            for ($i = 0; $i < $token['claimQuantity']; $i++) {
                                $claims->push([
                                    'beam_id' => $beam->id,
                                    'token_chain_id' => $tokenId,
                                    'collection_id' => $beam->collection->id,
                                    'type' => $token['type'],
                                    'code' => $singleUse ? bin2hex(openssl_random_pseudo_bytes(16)) : null,
                                    'nonce' => $singleUse ? 1 : null,
                                    'attributes' => json_encode($token['attributes']) ?: null,
                                    'quantity' => $token['quantity'],
                                ]);
                            }
                        } else {
                            LazyCollection::make(function () use ($range) {
                                $from = $range[0];
                                $to = $range[1];
                                while ($from <= $to) {
                                    yield $from;
                                    $from = bcadd($from, 1);
                                }
                            })->chunk(10000)->each(function (LazyCollection $tokenIds) use ($beam, $singleUse, $token) {
                                $claims = collect();
                                $tokenIds->each(function ($tokenId) use ($token, $beam, $singleUse, $claims) {
                                    for ($i = 0; $i < $token['claimQuantity']; $i++) {
                                        $claims->push([
                                            'beam_id' => $beam->id,
                                            'token_chain_id' => $tokenId,
                                            'collection_id' => $beam->collection->id,
                                            'type' => $token['type'],
                                            'code' => $singleUse ? bin2hex(openssl_random_pseudo_bytes(16)) : null,
                                            'nonce' => $singleUse ? 1 : null,
                                            'attributes' => json_encode($token['attributes']) ?: null,
                                            'quantity' => $token['quantity'],
                                        ]);
                                    }
                                });
                                $claims->chunk(5000)->each(fn (Collection $claims) => CreateBeamClaims::dispatch($claims));
                                unset($tokenIds, $claims);
                            });
                        }
                    });
                });
                $claims->chunk(5000)->each(fn (Collection $claims) => CreateBeamClaims::dispatch($claims));
                unset($chunks, $claims);
            });

        unset($tokens, $this->beam, $this->tokens);
    }
}
