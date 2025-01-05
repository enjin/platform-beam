<?php

return [
    'beam_flag.description' => 'Defines a flag setting for the beam. The `flag` specifies the type of behavior to modify, while `enabled` determines whether the selected flag is active. Use to enable or disable specific features of the beam.',
    'beam_flag.field.flag' => 'Specifies the type of beam behavior to modify.',
    'beam_flag.field.enabled' => 'Indicates whether the specified flag is active (`true`) or inactive (`false`).',
    'claim_token.description' => 'Specifies the tokens included in a beam, along with their claimable amounts, distribution methods, and attributes. Based on the token configuration, the system generates "Beam Claims," where each claim mints or transfers a single token id (with a specified quantity) to the claimer\'s account.',
    'claim_token.field.tokenId' => 'Specifies a list of [integer token IDs](https://docs.enjin.io/docs/tokenid-structure#4-integer) claimable in the beam. Provide IDs as strings, using commas to separate multiple entries. Supports single IDs (e.g., `"1"`), ranges (e.g., `"6..8"`), or a combination (e.g., `["1", "6..8", "10"]` includes IDs 1, 6, 7, 8, and 10).',
    'claim_token.field.tokenIdDataUpload' => 'You can use this to upload a txt file that contains a list of token ID ranges, one per line.',
    'claim_token.field.claimQuantity' => 'Specifies the number of claims to create for each provided token ID. Defaults to `1`.',
    'claim_token.field.tokenQuantityPerClaim' => 'Specifies how many token units will be minted or transferred for each claim.',
    'beam_pack.description' => 'The beam pack.',
    'beam_pack.field.id' => 'The beam pack database ID, which can be null when creating a new beam pack.',
    'beam_pack.field.beam_pack' => 'The number of times this pack can be claimed.',
];
