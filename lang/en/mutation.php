<?php

return [
    'claim_beam.args.account' => 'The wallet account.',
    'claim_beam.args.code' => 'The beam code.',
    'claim_beam.args.single_use_code' => 'The beam single use code.',
    'claim_beam.args.cryptoSignatureType' => 'The signature crypto type. This field is optional and it will use sr25519 by default.',
    'claim_beam.args.signature' => 'The signed message.',
    'claim_beam.description' => 'Mutation for claiming a beam.',
    'common.args.description' => 'The beam description.',
    'common.args.end' => 'Specifies the date and time when the beam becomes unclaimable (e.g., "2029-12-31T11:59:59Z").',
    'common.args.flags' => 'The beam flags.',
    'common.args.image' => 'The beam image URL.',
    'common.args.name' => 'The beam name.',
    'common.args.quantityPerClaim' => 'The quantity per claim.',
    'common.args.start' => 'Specifies the date and time when the beam becomes claimable (e.g., "2026-01-01T00:00:00Z").',
    'common.args.type' => <<<'MD'
Specifies how tokens are delivered:  
- **`TRANSFER_TOKEN`**: Transfers existing tokens to the recipient.  
- **`MINT_ON_DEMAND`**: Mints tokens directly to the recipient.  
MD,
    'common.args.source' => <<<'MD'
(Optional) Specifies the wallet account from which tokens will be distributed. By default, tokens are distributed from the collection owner's account.  

- For `TRANSFER_TOKEN` beamType: The source account acts as the operator and must be approved to transfer the token before a claim is transferred. [Learn more about operator transfers and approvals](https://docs.enjin.io/docs/multitoken-pallet#operator-transfer).  
- For `MINT_ON_DEMAND` beamType: The source account is ignored, and tokens are always minted from the collection owner's account.  
MD,
    'create_beam.args.collectionId' => 'The collection ID to distribute tokens from.',
    'create_beam.args.tokenIds' => 'The token chain IDs to claim.',
    'create_beam.description' => 'Creates a new Beam, a QR code-based distribution system for tokens. Beams allow users to claim tokens directly into their Enjin Wallet. [Learn more](https://docs.enjin.io/docs/create-qr-drops).',
    'update_beam.args.flags' => 'Specifies a list of flags to customize the behavior and functionality of the beam. Flags can control features like pausing claims, enabling single-use claim codes, or other beam-specific settings.',
    'update_beam.description' => 'Updates an existing beam, allowing modifications to its metadata (e.g., name, description) and configuration (e.g., timing, flags, tokens, and more).',
    'claim_beam.field.claimedAt' => 'The claim timestamp.',
    'claim_beam.field.claimStatus' => 'The claim status.',
    'expire_single_use_codes.description' => 'Expire single use codes.',
    'expire_single_use_codes.args.codes' => 'An array single use codes.',
    'delete_beam.description' => 'Deletes a beam, making it unclaimable and removing its availability for claims.',
    'remove_tokens.description' => 'Removes tokens from a beam.',
    'remove_tokens.args.tokenIds' => 'The token IDs to remove.',
    'add_tokens.description' => 'Add tokens to a beam.',
];
