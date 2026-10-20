<?php

return [
    'beta' => [
        'enabled' => (bool) env('VITALEEVO_BETA_MODE', false),
        'max_tenants' => (int) env('VITALEEVO_BETA_MAX_TENANTS', 5),
        'max_professionals' => (int) env('VITALEEVO_BETA_MAX_PROFESSIONALS', 10),
    ],
];
