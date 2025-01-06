<?php

return [
    'beam_flag.description' => <<<MD
Specifies the type of beam behavior to modify. Available options include:  

- **`PAUSED`**: Temporarily prevents claims from being processed.  
- **`SINGLE_USE`**: Enables the use of single-use claim codes for distributing specific tokens from the beam. [Learn more](https://docs.enjin.io/docs/create-qr-drops#option-b-using-the-enjin-api--sdks)  
- **`PRUNABLE`**: Allows claim records for expired beams to be deleted from the database after a set period.  
MD,
    'beam_type.description' => <<<MD
Specifies how tokens are delivered:  
- **`TRANSFER_TOKEN`**: Transfers existing tokens to the recipient.  
- **`MINT_ON_DEMAND`**: Mints tokens directly to the recipient.  
MD,
    'claim_status.description' => 'The claim status can be Pending, InProgress, Completed or Failed',
];
