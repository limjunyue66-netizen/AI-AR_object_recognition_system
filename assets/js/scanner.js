/**
 * Main scanner controller: capture → recognize → AR overlay.
 */
(function () {
  'use strict';

  const els = {
    video: document.getElementById('cameraVideo'),
    stage: document.getElementById('cameraStage'),
    scanBtn: document.getElementById('scanBtn'),
    status: document.getElementById('statusMessage'),
    error: document.getElementById('errorBanner'),
    progress: document.getElementById('progressPanel'),
    progressFill: document.getElementById('progressFill'),
    progressTitle: document.getElementById('progressTitle'),
    steps: {
      capture: document.getElementById('stepCapture'),
      ai: document.getElementById('stepAi'),
      lookup: document.getElementById('stepLookup'),
      display: document.getElementById('stepDisplay'),
    },
    frame: document.getElementById('scanFrame'),
    arBox: document.getElementById('arBox'),
    arCard: document.getElementById('arCard'),
    torchBtn: document.getElementById('torchBtn'),
    switchBtn: document.getElementById('switchBtn'),
    speakBtn: document.getElementById('speakBtn'),
    stopVoiceBtn: document.getElementById('stopVoiceBtn'),
    replayBtn: document.getElementById('replayBtn'),
    reticle: document.getElementById('focusReticle'),
  };

  let busy = false;
  let hasResult = false;
  let reticleTimer = null;
  let lastResult = null;

  function apiBase() {
    const meta = document.querySelector('meta[name="app-base"]');
    return meta ? meta.content.replace(/\/$/, '') : '';
  }

  function setStatus(text) {
    if (els.status) {
      els.status.textContent = text;
    }
  }

  function showError(message) {
    if (!els.error) return;
    els.error.textContent = message;
    els.error.classList.add('visible');
  }

  function hideError() {
    if (els.error) els.error.classList.remove('visible');
  }

  function setButton(mode) {
    if (!els.scanBtn) return;
    if (mode === 'scanning') {
      els.scanBtn.textContent = 'SCANNING...';
      els.scanBtn.disabled = true;
      els.scanBtn.setAttribute('aria-busy', 'true');
    } else if (mode === 'again') {
      els.scanBtn.textContent = 'SCAN AGAIN';
      els.scanBtn.disabled = false;
      els.scanBtn.setAttribute('aria-busy', 'false');
    } else {
      els.scanBtn.textContent = 'SCAN';
      els.scanBtn.disabled = false;
      els.scanBtn.setAttribute('aria-busy', 'false');
    }
  }

  function setProgress(visible, title, percent, activeStep) {
    if (!els.progress) return;
    els.progress.classList.toggle('visible', visible);
    if (els.progressTitle && title) els.progressTitle.textContent = title;
    if (els.progressFill) els.progressFill.style.width = Math.max(8, Math.min(100, percent)) + '%';

    const order = ['capture', 'ai', 'lookup', 'display'];
    const activeIndex = order.indexOf(activeStep);
    order.forEach((key, idx) => {
      const li = els.steps[key];
      if (!li) return;
      li.classList.remove('done', 'active');
      const dot = li.querySelector('.dot');
      if (idx < activeIndex) {
        li.classList.add('done');
        if (dot) dot.textContent = '✓';
      } else if (idx === activeIndex) {
        li.classList.add('active');
        if (dot) dot.textContent = '●';
      } else {
        if (dot) dot.textContent = '○';
      }
    });
  }

  function completeProgress() {
    ['capture', 'ai', 'lookup', 'display'].forEach((key) => {
      const li = els.steps[key];
      if (!li) return;
      li.classList.add('done');
      li.classList.remove('active');
      const dot = li.querySelector('.dot');
      if (dot) dot.textContent = '✓';
    });
    if (els.progressFill) els.progressFill.style.width = '100%';
  }

  async function initCamera() {
    hideError();
    setStatus('Starting camera...');
    try {
      await ScannerCamera.start(els.video);
      ScannerCamera.onTorchChange = refreshCameraButtons;
      refreshCameraButtons();
      setStatus('Point camera at an object');
      setButton('idle');
    } catch (err) {
      const msg = err && err.message ? err.message : 'Camera is unavailable.';
      showError(msg);
      setStatus(msg);
      setButton('idle');
      if (els.scanBtn) els.scanBtn.disabled = true;
    }
  }

  async function runScan() {
    if (busy) return;
    busy = true;
    hideError();
    hasResult = false;
    lastResult = null;
    if (window.ScannerVoice) ScannerVoice.stop();
    ArOverlay.clear();
    if (els.frame) els.frame.style.opacity = '0.35';

    setButton('scanning');
    setStatus('Capturing image...');
    setProgress(true, 'Identifying Object', 20, 'capture');

    let dataUrl;
    ScannerCamera.captureLock = true;
    try {
      dataUrl = ScannerCamera.captureFrame(1024, 0.62);
    } catch (err) {
      busy = false;
      setProgress(false);
      setButton(hasResult ? 'again' : 'idle');
      const msg = err && err.message ? err.message : 'Unable to capture image.';
      showError(msg);
      setStatus(msg);
      if (els.frame) els.frame.style.opacity = '1';
      return;
    } finally {
      ScannerCamera.captureLock = false;
    }

    setStatus('AI identifying object...');
    setProgress(true, 'Identifying Object', 55, 'ai');

    const lang = (window.ClientPrefs && ClientPrefs.get('resultLang')) || 'en';
    const language = (window.APP_LANGUAGES || []).find((item) => item.code === lang);

    try {
      const response = await fetch(apiBase() + '/api/recognize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          image: dataUrl,
          target: lang,
          languageName: language ? language.name : '',
        }),
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch (_) {
        payload = null;
      }

      if (!response.ok || !payload || !payload.ok) {
        const errMsg = (payload && payload.error) ? payload.error : 'Unable to identify the object. Please try again.';
        throw new Error(errMsg);
      }

      setProgress(true, 'Identifying Object', 92, 'display');
      completeProgress();
      setProgress(false);

      const data = payload.data;
      lastResult = data;
      await ArOverlay.render(data, payload.meta && payload.meta.lang);
      hasResult = true;
      setStatus('Object identified');
      setButton('again');
      if (window.ScannerHaptic) ScannerHaptic.success();
      if (window.ScannerVoice && ClientPrefs.get('voiceEnabled') && ClientPrefs.get('autoSpeak')) {
        const spoken = ScannerVoice.speakText(
          ScannerVoice.buildSummary(ArOverlay.spokenFields()),
          ArOverlay.lang
        );
        if (!spoken.ok && spoken.error) setStatus(spoken.error);
      }
    } catch (err) {
      setProgress(false);
      const msg = err && err.message ? err.message : 'Unable to identify the object. Please try again.';
      showError(msg);
      setStatus(msg);
      setButton('again');
      if (window.ScannerHaptic) ScannerHaptic.error();
    } finally {
      busy = false;
      if (els.frame) els.frame.style.opacity = '1';
    }
  }

  function refreshCameraButtons() {
    if (!els.torchBtn) return;
    const supported = ScannerCamera.canTorch();
    const mode = ScannerCamera.torchMode();
    els.torchBtn.disabled = !supported;
    els.torchBtn.classList.toggle('is-on', supported && (mode === 'on' || ScannerCamera.torchOn));
    els.torchBtn.dataset.mode = mode;
    const titles = {
      auto: 'Flashlight auto. On in the dark, off in the light. Tap for always on.',
      on: 'Flashlight on. Tap to turn off.',
      off: 'Flashlight off. Tap for auto.',
    };
    els.torchBtn.title = supported ? (titles[mode] || titles.auto) : 'Flashlight is not supported on this device.';
    els.torchBtn.setAttribute('aria-pressed', ScannerCamera.torchOn ? 'true' : 'false');
  }

  function showReticle(clientX, clientY) {
    if (!els.reticle || !els.stage) return;
    const rect = els.stage.getBoundingClientRect();
    els.reticle.style.left = (clientX - rect.left) + 'px';
    els.reticle.style.top = (clientY - rect.top) + 'px';
    els.reticle.classList.remove('show');
    void els.reticle.offsetWidth;
    els.reticle.classList.add('show');
    clearTimeout(reticleTimer);
    reticleTimer = setTimeout(() => els.reticle.classList.remove('show'), 1300);
  }

  function bindEvents() {
    if (els.scanBtn) {
      els.scanBtn.addEventListener('click', runScan);
    }

    if (els.stage) {
      els.stage.addEventListener('click', (e) => {
        if (!ClientPrefs.get('tapToFocus')) return;
        if (e.target.closest && e.target.closest('button, a, .result-panel')) return;
        showReticle(e.clientX, e.clientY);
        if (window.ScannerHaptic) ScannerHaptic.focus();
        ScannerCamera.focusAt(e.clientX, e.clientY, els.stage);
      });
    }

    if (els.torchBtn) {
      els.torchBtn.addEventListener('click', async () => {
        const result = await ScannerCamera.cycleTorchMode();
        refreshCameraButtons();
        if (!result.ok) setStatus(result.error || 'Flashlight is not supported on this device.');
        else if (result.mode === 'auto') setStatus('Flashlight auto: on in the dark, off when it is bright.');
        else if (result.mode === 'on') setStatus('Flashlight on.');
        else setStatus('Flashlight off.');
      });
    }

    if (els.switchBtn) {
      els.switchBtn.addEventListener('click', async () => {
        els.switchBtn.disabled = true;
        try {
          await ScannerCamera.switchNext(els.video);
          refreshCameraButtons();
          setStatus(ScannerCamera.facingMode === 'user' ? 'Front camera' : 'Back camera');
        } catch (err) {
          setStatus(err && err.message ? err.message : 'Unable to switch camera.');
        } finally {
          els.switchBtn.disabled = false;
        }
      });
    }

    if (els.speakBtn) {
      els.speakBtn.addEventListener('click', () => {
        const result = ScannerVoice.speakVisibleCard(els.arCard, window.ArOverlay ? ArOverlay.lang : '');
        if (!result.ok) setStatus(result.error);
      });
    }

    if (els.replayBtn) {
      els.replayBtn.addEventListener('click', () => {
        const result = ScannerVoice.replay();
        if (!result.ok) setStatus(result.error);
      });
    }

    if (els.stopVoiceBtn) {
      els.stopVoiceBtn.addEventListener('click', () => ScannerVoice.stop());
    }

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        ScannerVoice.stop();
        return;
      }
      if (!ScannerCamera.stream) {
        initCamera();
      }
    });

    window.addEventListener('pagehide', () => {
      ScannerVoice.stop();
      ScannerCamera.stop();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    ArOverlay.init({
      boxEl: els.arBox,
      cardEl: els.arCard,
      stageEl: els.stage,
      videoEl: els.video,
    });
    bindEvents();
    initCamera();
  });
})();
