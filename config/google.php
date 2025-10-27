<?php

return [
    'service_account_credentials_path' => base_path(env('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS_RELATIVE_PATH')), // CAMBIA EL NOMBRE DE LA VAR EN ENV también para claridad
    'sheet_id' => env('GOOGLE_SHEET_ID'),
];
