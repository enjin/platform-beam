<?php

namespace Enjin\Platform\Beam;

use Enjin\Platform\Beam\Commands\BatchProcess;
use Enjin\Platform\Beam\Listeners\ExpireBeam;
use Enjin\Platform\Beam\Listeners\PauseBeam;
use Enjin\Platform\Beam\Listeners\RemoveClaimToken;
use Enjin\Platform\Beam\Listeners\UnpauseBeam;
use Enjin\Platform\Beam\Listeners\UpdateClaimCollectionIds;
use Enjin\Platform\Beam\Listeners\UpdateClaimStatus;
use Enjin\Platform\Events\Global\TransactionUpdated;
use Enjin\Platform\Events\Substrate\Commands\PlatformSynced;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionDestroyed;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionFrozen;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionThawed;
use Enjin\Platform\Events\Substrate\MultiTokens\TokenBurned;
use Enjin\Platform\Events\Substrate\MultiTokens\TokenDestroyed;
use Enjin\Platform\Events\Substrate\MultiTokens\TokenTransferred;
use Enjin\Platform\Beam\Package as BeamPackage;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BeamServiceProvider extends PackageServiceProvider
{
    /**
     * Configure provider.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('platform-beam')
            ->hasConfigFile(['enjin-platform-beam'])
            ->hasCommands([BatchProcess::class])
            ->hasMigration('create_beams_table')
            ->hasMigration('create_beam_claims_table')
            ->hasMigration('create_beam_scans_table')
            ->hasMigration('update_beams_table')
            ->hasMigration('add_collection_chain_id_to_beam_batches_table')
            ->hasMigration('add_idempotency_key_to_beam_claims_table')
            ->hasMigration('add_source_to_beams_table')
            ->hasRoute('enjin-platform-beam')
            ->hasTranslations();
    }

    /**
     * Register provider.
     *
     * @return void
     */
    #[\Override]
    public function register()
    {
        if (app()->runningUnitTests()) {
            BeamPackage::setPath(__DIR__ . '/..');
        }

        parent::register();

        Event::listen(TransactionUpdated::class, UpdateClaimStatus::class);
        Event::listen(TokenBurned::class, RemoveClaimToken::class);
        Event::listen(TokenDestroyed::class, RemoveClaimToken::class);
        Event::listen(TokenTransferred::class, RemoveClaimToken::class);
        Event::listen(CollectionFrozen::class, PauseBeam::class);
        Event::listen(CollectionThawed::class, UnpauseBeam::class);
        Event::listen(CollectionDestroyed::class, ExpireBeam::class);
        Event::listen(PlatformSynced::class, UpdateClaimCollectionIds::class);
    }

    /**
     * Boot provider.
     *
     * @return void
     */
    #[\Override]
    public function boot()
    {
        parent::boot();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/enjin-platform-beam.php');
    }

    public function packageRegistered()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'enjin-platform-beam');
    }
}
