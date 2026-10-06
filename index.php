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
  <title>AI + AR Smart Object Recognition</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="<?= app_h($base) ?>/assets/css/app.css?v=20260928j">
</head>
<body class="scanner-page">
  <main class="scanner-shell" aria-label="AI AR object scanner">
    <div class="camera-stage" id="cameraStage">
      <video id="cameraVideo" playsinline muted autoplay aria-label="Live camera preview"></video>
      <div class="focus-reticle" id="focusReticle" aria-hidden="true"><span></span></div>
    </div>

    <div class="scan-overlay" aria-hidden="true">
      <div class="scan-frame" id="scanFrame">
        <div class="scan-corners"><i></i><i></i></div>
        <div class="scan-line"></div>
      </div>
    </div>

    <div class="ar-layer" id="arLayer">
      <div class="ar-box" id="arBox" aria-hidden="true">
        <span class="ar-label">Object</span>
      </div>
    </div>

    <!-- Fixed top-left result panel (outside ar-layer so it never follows the box) -->
    <aside class="result-panel" id="arCard" hidden aria-label="Recognition result card">
      <div class="result-panel__top">
        <h2 class="result-panel__title" data-field="productName">Product</h2>
        <button type="button" class="lang-select" id="langOpen" aria-haspopup="dialog" aria-controls="langSheet">English</button>
      </div>
      <button type="button" class="expand-btn" id="expandBtn" aria-expanded="false">
        <span data-i18n="expand">Show details</span>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="result-panel__body" id="resultBody" hidden>
        <dl>
          <div class="detail-row"><dt data-i18n="manufacturer">Manufacturer</dt><dd data-field="manufacturer">—</dd></div>
          <div class="detail-row"><dt data-i18n="specification">Specification</dt><dd data-field="specification">—</dd></div>
          <div class="detail-row"><dt data-i18n="description">Description</dt><dd data-field="description">—</dd></div>
          <div class="detail-row"><dt data-i18n="confidence">Confidence</dt><dd data-field="confidence">—</dd></div>
          <div class="detail-row"><dt data-i18n="provider">Provider</dt><dd data-field="provider">—</dd></div>
        </dl>
      </div>
    </aside>

    <header class="top-bar">
      <div class="brand-block">
        <h1>AI + AR OBJECT SCANNER</h1>
        <p>Smart object recognition</p>
      </div>
      <div style="display:flex;gap:8px;">
        <a class="icon-btn" href="<?= app_h($base) ?>/history.php" aria-label="Scan history" title="History">
          <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
        </a>
        <a class="icon-btn" href="<?= app_h($base) ?>/settings.php" aria-label="Open settings" title="Settings">
          <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </a>
      </div>
    </header>

    <div class="error-banner" id="errorBanner" role="alert"></div>

    <div class="progress-panel" id="progressPanel" role="status" aria-live="polite">
      <h3 id="progressTitle">Identifying Object</h3>
      <div class="progress-track" aria-hidden="true">
        <div class="progress-fill" id="progressFill"></div>
      </div>
      <ul class="progress-steps">
        <li id="stepCapture"><span class="dot">○</span> Capture image</li>
        <li id="stepAi"><span class="dot">○</span> AI identification</li>
        <li id="stepLookup"><span class="dot">○</span> Product lookup</li>
        <li id="stepDisplay"><span class="dot">○</span> Display result</li>
      </ul>
    </div>

    <footer class="bottom-bar">
      <div class="cam-dock" role="toolbar" aria-label="Camera and voice controls">
        <button type="button" class="dock-btn" id="torchBtn" data-mode="auto" aria-pressed="false" aria-label="Flashlight mode" title="Flashlight auto. Tap for always on.">
          <i class="fa-solid fa-bolt" aria-hidden="true"></i>
        </button>
        <button type="button" class="dock-btn" id="switchBtn" aria-label="Switch camera" title="Switch camera">
          <i class="fa-solid fa-camera-rotate" aria-hidden="true"></i>
        </button>
        <button type="button" class="dock-btn" id="speakBtn" aria-label="Speak current result" title="Speak current result">
          <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
        </button>
        <button type="button" class="dock-btn" id="stopVoiceBtn" aria-label="Stop voice" title="Stop voice">
          <i class="fa-solid fa-stop" aria-hidden="true"></i>
        </button>
        <button type="button" class="dock-btn" id="replayBtn" aria-label="Replay last narration" title="Replay the last spoken sentence">
          <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
        </button>
      </div>
      <div class="status-pill" id="statusMessage" role="status" aria-live="polite">
        Point camera at an object
      </div>
      <button type="button" class="scan-btn" id="scanBtn" aria-label="Scan object">SCAN</button>
    </footer>
  </main>

  <div class="lang-sheet" id="langSheet" hidden role="dialog" aria-modal="true" aria-label="Choose language">
    <div class="lang-sheet__panel">
      <div class="lang-sheet__head">
        <h2>Language</h2>
        <button type="button" class="secondary-btn" id="langClose">Close</button>
      </div>
      <input type="search" id="langSearch" placeholder="Search language" aria-label="Search language" autocomplete="off">
      <div class="lang-list" id="langList"></div>
    </div>
  </div>

  <script src="<?= app_h($base) ?>/assets/js/languages.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/client-prefs.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/haptic.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/voice.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/camera.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/ar-overlay.js?v=20260928j"></script>
  <script src="<?= app_h($base) ?>/assets/js/scanner.js?v=20260928j"></script>
</body>
</html>
