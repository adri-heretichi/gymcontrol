<?php

return [
    'allow_local_mysql' => filter_var(env('FENIX_DEMO_ALLOW_LOCAL_MYSQL', false), FILTER_VALIDATE_BOOL),
    'prices' => [
        'Krav Maga Kids' => env('FENIX_DEMO_PRICE_KRAV_MAGA_KIDS'),
        'Kapap Krav Maga' => env('FENIX_DEMO_PRICE_KAPAP_KRAV_MAGA'),
        'Kickboxing Muay Thai' => env('FENIX_DEMO_PRICE_KICKBOXING_MUAY_THAI'),
        'Funcional' => env('FENIX_DEMO_PRICE_FUNCIONAL'),
    ],
];
