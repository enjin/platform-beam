<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DispatchCreateBeamClaimsJobs implements ShouldQueue
{
    use Dispatchable;
    use IntegerRange;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Model $beam,
        protected ?array $tokens,
        protected ?int $packId = null
    ) {
        $this->onQueue(config('enjin-platform-beam.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $beam = $this->beam->load('collection');
        if (!$beam?->collection) {
            Log::error('Beam collection not found', ['beam_id' => $beam->id]);

            return;
        }


        $tokens = collect($this->tokens);

        $tokens->chunk(1000)
            ->each(function (Collection $chunks) use ($beam): void {
                $claims = collect();
                $chunks->each(function ($token) use ($beam, $claims): void {
                    collect($token['tokenIds'])->each(function ($tokenId) use ($beam, $claims, $token): void {
                        $range = $this->integerRange($tokenId);
                        if ($range === false) {
                            for ($i = 0; $i < Arr::get($token, 'claimQuantity', 1); $i++) {
                                $claims->push([
                                    'beam_id' => $beam->id,
                                    'token_chain_id' => $tokenId,
                                    'collection_id' => $beam->collection->id,
                                    'type' => $token['type'],
                                    'code' => bin2hex(openssl_random_pseudo_bytes(16)),
                                    'nonce' => 1,
                                    'attributes' => json_encode($token['attributes']) ?: null,
                                    'quantity' => $token['quantity'],
                                    'beam_pack_id' => $this->packId,
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
                            })->chunk(10000)->each(function (LazyCollection $tokenIds) use ($beam, $token): void {
                                $claims = collect();
                                $tokenIds->each(function ($tokenId) use ($token, $beam, $claims) {
                                    for ($i = 0; $i < Arr::get($token, 'claimQuantity', 1); $i++) {
                                        $claims->push([
                                            'beam_id' => $beam->id,
                                            'token_chain_id' => $tokenId,
                                            'collection_id' => $beam->collection->id,
                                            'type' => $token['type'],
                                            'code' => bin2hex(openssl_random_pseudo_bytes(16)),
                                            'nonce' => 1,
                                            'attributes' => json_encode($token['attributes']) ?: null,
                                            'quantity' => $token['quantity'],
                                            'beam_pack_id' => $this->packId,
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
