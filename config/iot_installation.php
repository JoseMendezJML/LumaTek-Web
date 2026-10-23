<?php

return [
    'firmware_root' => storage_path('app/private/iot-firmware'),
    // Reviewed distribution artifacts only. Never point this at laboratory firmware.
    'releases' => [
        'esp32-devkit-planned' => [
            'model' => 'esp32_devkit_v1',
            'label' => 'ESP32 DevKit V1',
            'version' => null,
            'date' => null,
            'status' => 'planned',
            'approved' => false,
            'reviewed_by' => null,
            'approved_at' => null,
            'secrets_reviewed' => false,
            'instructions' => 'No hay firmware aprobado para distribución. La versión de laboratorio no se publica.',
            'artifacts' => [],
        ],
    ],
];
