<?php

namespace Enjin\Platform\Beam\Services;

use Illuminate\Support\Facades\DB;

class BeamClaimService
{
    public function syncCollectionIds()
    {
        DB::statement('
            UPDATE beam_claims AS dest,
            (
              SELECT
                  b.id AS beamId,
                  b.collection_chain_id AS beamCollectionChainId,
                  c.id AS collectionId
              FROM
                  beams b
              JOIN
                  collections c on b.collection_chain_id = c.collection_chain_id
            ) AS src 
            SET dest.collection_id = src.collectionId
            WHERE dest.beam_id = src.beamId
        ');
    }
}
