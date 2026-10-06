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
  <title>Scan History · AI + AR Scanner</title>
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
          <h1>Scan History</h1>
          <p>Recent object recognition results</p>
        </div>
        <div class="nav-actions">
          <a class="secondary-btn" href="<?= app_h($base) ?>/index.php"><i class="fa-solid fa-camera" aria-hidden="true"></i> Scanner</a>
          <a class="secondary-btn" href="<?= app_h($base) ?>/settings.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
        </div>
      </header>

      <div class="panel">
        <div id="historyNotice" class="error-banner" style="position:static;transform:none;width:auto;margin-bottom:12px;display:none;"></div>

        <div class="history-toolbar">
          <input type="search" id="historySearch" placeholder="Search product, brand, object..." aria-label="Search history">
          <button type="button" class="danger-btn" id="clearHistory">Clear history</button>
        </div>

        <div class="table-wrap">
          <table class="history-table" aria-label="Scan history table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Provider</th>
                <th>Confidence</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr><td colspan="5">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <button type="button" class="secondary-btn" id="prevPage">Previous</button>
          <span id="historyMeta" aria-live="polite">—</span>
          <button type="button" class="secondary-btn" id="nextPage">Next</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="detailModal" role="dialog" aria-modal="true" aria-label="Scan details">
    <div class="modal">
      <h3>Scan details</h3>
      <div id="modalBody"></div>
      <div class="btn-row">
        <button type="button" class="secondary-btn" id="modalClose">Close</button>
      </div>
    </div>
  </div>

  <script src="<?= app_h($base) ?>/assets/js/history.js"></script>
</body>
</html>
