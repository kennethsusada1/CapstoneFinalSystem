<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // -----------------------------------------------------------------------
    // PMS ↔ L&D Integration
    // -----------------------------------------------------------------------

    // Inbound: smart-pms calls our API with this token.
    // We generate it; PMS stores it as LND_API_TOKEN in their .env.
    'lnd' => [
        'api_token'          => env('LND_API_TOKEN'),
        'redirect_hmac_secret' => env('LND_REDIRECT_HMAC_SECRET'),
    ],

    // Outbound: we call PMS callback endpoint with PMS_CALLBACK_TOKEN.
    // PMS generates it; we store it here.
    'pms' => [
        'base_url'       => env('PMS_BASE_URL'),
        'callback_token' => env('PMS_CALLBACK_TOKEN'),
        'timeout'        => env('PMS_TIMEOUT', 20),
    ],

    'lna' => [
        'model_path' => env('LNA_MODEL_PATH', resource_path('models/lna_logistic_model.json')),
        'threshold' => (float) env('LNA_MODEL_THRESHOLD', 0.5),
        'auto_training' => [
            'enabled' => env('LNA_AUTO_TRAINING_ENABLED', true),
            'min_rows' => (int) env('LNA_AUTO_TRAINING_MIN_ROWS', 100),
            'min_positive_rows' => (int) env('LNA_AUTO_TRAINING_MIN_POSITIVE_ROWS', 20),
            'min_negative_rows' => (int) env('LNA_AUTO_TRAINING_MIN_NEGATIVE_ROWS', 20),
            'min_years' => (int) env('LNA_AUTO_TRAINING_MIN_YEARS', 2),
            'min_validation_rows' => (int) env('LNA_AUTO_TRAINING_MIN_VALIDATION_ROWS', 30),
            'min_validation_roc_auc' => (float) env('LNA_AUTO_TRAINING_MIN_VALIDATION_ROC_AUC', 0.70),
            'python_binary' => env('LNA_PYTHON_BIN', 'python'),
            'trainer_script' => env('LNA_TRAINER_SCRIPT', 'scripts/train_lna_model.py'),
            'timeout' => (int) env('LNA_AUTO_TRAINING_TIMEOUT', 600),
        ],
    ],

];
