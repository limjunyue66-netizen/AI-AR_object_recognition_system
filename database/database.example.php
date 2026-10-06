<?php

declare(strict_types=1);

/**
 * Example MySQL connection settings for optional scan history.
 *
 * How to use:
 * 1. Import database/database.sql in phpMyAdmin / MySQL.
 * 2. Copy the values below into config/config.php (preferred),
 *    or merge them with your local AppConfig settings via Settings UI.
 * 3. Never commit real passwords or production credentials.
 *
 * The live app reads DB settings from config/config.php:
 *   db_host, db_port, db_name, db_user, db_pass, db_enabled
 */
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'ai_ar_scanner',
    'db_user' => 'root',
    // Put your local MySQL password here when copying into config.php
    'db_pass' => 'YOUR_DB_PASSWORD',
    // Set true only after database.sql is imported
    'db_enabled' => false,
];
