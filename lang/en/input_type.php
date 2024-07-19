<?php

return [
    'beam_flag.description' => 'The beam flag input type.',
    'beam_flag.field.flag' => 'The beam flag.',
    'beam_flag.field.enabled' => 'The flag enabled status.',
    'claim_token.description' => 'The claimable tokens.',
    'claim_token_pack.description' => 'The claimable tokens in a beam pack.',
    'claim_token.field.tokenId' => 'The token chain IDs available to claim.',
    'claim_token.field.tokenIdDataUpload' => 'You can use this to upload a txt file that contains a list of token ID ranges, one per line.',
    'claim_token.field.claimQuantity' => 'The total amount of times each token ID can be claimed.  This is mainly relevant for fungible tokens, where you can specify that there are a certain amount of claims for a token ID, e.g. 10 individual claims to receive 1 token with ID 123 per claim. Ignored when creating a beam pack.',
    'claim_token.field.tokenQuantityPerClaim' => 'The quantity of token that can be received per claim.',
];
