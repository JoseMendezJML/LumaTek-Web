<?php

return [
    // Retain terminal challenges for audit; never store plaintext device secrets.
    'retention_days' => 7,
    // Enable only after explicit approval for the scheduler and target database.
    'schedule_enabled' => false,
];
