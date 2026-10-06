<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$base = app_base_path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="app-base" content="<?= app_h($base) ?>">
  <meta name="theme-color" content="#070b12">
  <title>Settings · AI + AR Scanner</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="<?= app_h($base) ?>/assets/css/app.css">
</head>
<body>
  <div class="page">
    <div class="page-wrap">
      <header class="page-header">
        <div>
          <h1>Settings</h1>
          <p>Configure AI providers and scanner options</p>
        </div>
        <div class="nav-actions">
          <a class="secondary-btn" href="<?= app_h($base) ?>/index.php"><i class="fa-solid fa-camera" aria-hidden="true"></i> Scanner</a>
          <a class="secondary-btn" href="<?= app_h($base) ?>/history.php"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
        </div>
      </header>

      <form id="settingsForm" class="panel" autocomplete="off">
        <h2>AI Provider</h2>
        <div class="form-grid">
          <label>
            Provider mode
            <select id="ai_provider" name="ai_provider" aria-label="AI provider mode">
              <option value="auto">Auto (Gemini → Agnes)</option>
              <option value="gemini">Gemini only</option>
              <option value="agnes">Agnes only</option>
            </select>
          </label>

          <div class="form-grid two">
            <label>
              Gemini model
              <input type="text" id="gemini_model" name="gemini_model" placeholder="gemini-2.0-flash" aria-label="Gemini model">
            </label>
            <label>
              Agnes model
              <input type="text" id="agnes_model" name="agnes_model" placeholder="agnes-2.5-flash" aria-label="Agnes model">
            </label>
          </div>

          <div class="form-grid two">
            <label>
              Gemini fallback models
              <textarea id="gemini_fallback_models" name="gemini_fallback_models" placeholder="One model per line" aria-label="Gemini fallback models"></textarea>
            </label>
            <label>
              Agnes fallback models
              <textarea id="agnes_fallback_models" name="agnes_fallback_models" placeholder="One model per line" aria-label="Agnes fallback models"></textarea>
            </label>
          </div>

          <label>
            Agnes base URL
            <input type="text" id="agnes_base_url" name="agnes_base_url" placeholder="https://apihub.agnes-ai.com/v1" aria-label="Agnes base URL">
          </label>

          <label>
            Request timeout (10–120 seconds)
            <input type="number" id="request_timeout" name="request_timeout" min="10" max="120" step="1" aria-label="Request timeout">
          </label>

          <label>
            Gemini API key
            <span class="key-status" id="gemini_key_status">Not configured</span>
            <div class="key-row">
              <input type="password" id="gemini_api_key" name="gemini_api_key" placeholder="Leave blank to keep saved key" autocomplete="new-password" aria-label="Gemini API key">
              <button type="button" class="secondary-btn" id="toggleGeminiKey">Show</button>
            </div>
          </label>

          <label>
            Agnes API key
            <span class="key-status" id="agnes_key_status">Not configured</span>
            <div class="key-row">
              <input type="password" id="agnes_api_key" name="agnes_api_key" placeholder="Leave blank to keep saved key" autocomplete="new-password" aria-label="Agnes API key">
              <button type="button" class="secondary-btn" id="toggleAgnesKey">Show</button>
            </div>
          </label>

          <label style="display:flex;align-items:center;gap:10px;color:var(--text);">
            <input type="checkbox" id="web_lookup_enabled" name="web_lookup_enabled">
            Enable optional web product lookup after AI recognition
          </label>

          <label style="display:flex;align-items:center;gap:10px;color:var(--text);">
            <input type="checkbox" id="db_enabled" name="db_enabled">
            Use MySQL for history (optional — SQLite is used by default)
          </label>
        </div>

        <div class="btn-row">
          <button type="submit" class="primary-btn">Save Settings</button>
          <button type="button" class="secondary-btn" id="testBtn">Test Connection</button>
        </div>
      </form>

      <section class="panel" aria-label="Camera settings">
        <h2>Camera Settings</h2>
        <div class="form-grid">
          <label>
            Camera Resolution
            <select id="prefResolution" aria-label="Camera resolution">
              <option value="auto">Auto</option>
              <option value="standard">Standard</option>
              <option value="720p">HD 720p</option>
              <option value="1080p">Full HD 1080p</option>
              <option value="high">High Resolution</option>
            </select>
          </label>
          <label class="toggle-row">Autofocus <input type="checkbox" id="prefAutofocus"></label>
          <label class="toggle-row">Tap to Focus <input type="checkbox" id="prefTapFocus"></label>
          <label class="toggle-row">Haptic Feedback <input type="checkbox" id="prefHaptic"></label>
          <label>
            Flashlight
            <select id="prefTorchMode" aria-label="Flashlight mode">
              <option value="auto">Auto (on in the dark)</option>
              <option value="on">Always on</option>
              <option value="off">Off</option>
            </select>
          </label>
          <p id="hapticNote" style="color:var(--muted);font-size:0.82rem;margin:0;"></p>
        </div>
      </section>

      <section class="panel" aria-label="Voice settings">
        <h2>Voice Settings</h2>
        <div class="form-grid">
          <label class="toggle-row">Voice Narration <input type="checkbox" id="prefVoiceEnabled"></label>
          <label class="toggle-row">Auto Speak <input type="checkbox" id="prefAutoSpeak"></label>
          <label>
            Voice
            <select id="prefVoice" aria-label="Narration voice">
              <option value="">Browser default</option>
            </select>
          </label>
          <label>
            Volume <span id="volumeLabel">100%</span>
            <input type="range" id="prefVolume" min="0" max="100" step="1" value="100" aria-label="Voice volume">
          </label>
          <div class="btn-row">
            <button type="button" class="primary-btn" id="testVoiceBtn">Test Voice</button>
            <button type="button" class="secondary-btn" id="stopVoiceBtn">Stop Voice</button>
            <button type="button" class="secondary-btn" id="resetClientPrefs">Reset camera &amp; voice</button>
          </div>
          <p id="voiceNote" style="color:var(--muted);font-size:0.82rem;margin:0;"></p>
        </div>
      </section>

      <section class="panel" aria-label="Connection test results">
        <h2>Test Connection</h2>
        <div class="test-results" id="testResults">
          <div class="test-item"><span>Run a test to check Gemini and Agnes.</span></div>
        </div>
      </section>

      <section class="panel" aria-label="Open on phone">
        <h2>Open on Phone</h2>
        <div class="qr-box">
          <img id="qrImage" alt="QR code to open scanner" width="220" height="220">
          <p>Scan this QR code using your phone.</p>
          <p id="qrUrl" style="color:var(--muted);font-size:0.85rem;word-break:break-all;"></p>
          <p style="color:var(--muted);font-size:0.82rem;">Camera access usually requires HTTPS (or localhost).</p>
        </div>
      </section>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <script src="<?= app_h($base) ?>/assets/js/client-prefs.js?v=20260928g"></script>
  <script src="<?= app_h($base) ?>/assets/js/voice.js?v=20260928g"></script>
  <script src="<?= app_h($base) ?>/assets/js/settings.js?v=20260928g"></script>
</body>
</html>
