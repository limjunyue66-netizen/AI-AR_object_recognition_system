<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and fill in your values.
 * Never commit config.php with real API keys or passwords.
 *
 *   copy config/config.example.php config/config.php
 *
 * Optional MySQL history: see also database/database.example.php
 * and import database/database.sql.
 */
return [
    // AI provider: auto | gemini | agnes
    'ai_provider' => 'auto',

    // Google Gemini Vision — get a key from Google AI Studio
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-2.0-flash',
    'gemini_fallback_models' => "gemini-1.5-flash\ngemini-1.5-pro",

    // Agnes AI Vision (OpenAI-compatible)
    'agnes_api_key' => '',
    'agnes_model' => 'agnes-2.5-flash',
    'agnes_fallback_models' => "agnes-2.0-flash",
    'agnes_base_url' => 'https://apihub.agnes-ai.com/v1',

    // Request timeout in seconds (10–120)
    'request_timeout' => 45,

    // Optional MySQL scan history (see database/database.example.php)
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'ai_ar_scanner',
    'db_user' => 'root',
    'db_pass' => '',
    'db_enabled' => false,

    // Optional simple settings API token (leave empty to allow local access)
    'settings_token' => '',

    // Enable optional web product enrichment after AI recognition
    'web_lookup_enabled' => true,
];
