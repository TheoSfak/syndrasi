<?php
/**
 * SynDrasi - Telegram Bot settings.
 *
 * Per-municipality settings override these defaults from Ρυθμίσεις Δήμου.
 */
return [
    'enabled'         => env('TELEGRAM_ENABLED', '0') === '1',
    'bot_token'       => env('TELEGRAM_BOT_TOKEN', ''),
    'command_chat_id' => env('TELEGRAM_COMMAND_CHAT_ID', ''),
    'team_chat_id'    => env('TELEGRAM_TEAM_CHAT_ID', ''),
];
