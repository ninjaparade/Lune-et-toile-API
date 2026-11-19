<?php

/*
 * Game settings that can change without shipping a new app build: edit the env value on the
 * server (then `php artisan config:cache` if config is cached). The app reads the results from
 * the API (`next_nudge_at` on games, `show_ads` on /me).
 */
return [

    // Minutes a player waits between nudges in the same game.
    'nudge_cooldown_minutes' => (int) env('NUDGE_COOLDOWN_MINUTES', 15),

    // Master switch for ads. Off = nobody sees ads, whatever their account says.
    'ads_enabled' => (bool) env('ADS_ENABLED', false),

    // Allowed values for player preferences, and the defaults for new players.
    'preferences' => [
        'undo_seconds' => [0, 3, 5, 10],
        'piece_sets' => ['rhosgfx', 'celtic', 'firi', 'california'],
        'defaults' => ['undo_seconds' => 0, 'piece_set' => 'rhosgfx'],
    ],

];
