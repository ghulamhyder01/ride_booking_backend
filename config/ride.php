<?php

return [
    'pricing' => [
        'economy' => [
            'base_fare' => 100,
            'per_km_rate' => 30,
        ],

        'comfort' => [
            'base_fare' => 150,
            'per_km_rate' => 40,
        ],
    ],

    'eta' => [
        // Assumed average speed in km/h; traffic is not considered.
        'average_speed_kmh' => 25,
    ],
];