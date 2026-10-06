/**
 * Settings page logic: load/save config, test providers, QR code.
 */
(function () {
  'use strict';

  function apiBase() {
    const meta = document.querySelector('meta[name="app-base"]');
    return meta ? meta.content.replace(/\/$/, '') : '';
  }

  const toast = document.getElementById('toast');

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 2800);
  }

  function fillForm(data) {
    setVal('ai_provider', data.ai_provider || 'auto');
    setVal('gemini_model', data.gemini_model || '');
    setVal('gemini_fallback_models', data.gemini_fallback_models || '');
    setVal('agnes_model', data.agnes_model || '');
    setVal('agnes_fallback_models', data.agnes_fallback_models || '');
    setVal('agnes_base_url', data.agnes_base_url || '');
    setVal('request_timeout', String(data.request_timeout || 45));

    const geminiStatus = document.getElementById('gemini_key_status');
    const agnesStatus = document.getElementById('agnes_key_status');
    if (geminiStatus) geminiStatus.textContent = data.gemini_key_status || 'Not configured';
    if (agnesStatus) agnesStatus.textContent = data.agnes_key_status || 'Not configured';

    const dbEnabled = document.getElementById('db_enabled');
    if (dbEnabled) dbEnabled.checked = !!data.db_enabled;

    const webLookup = document.getElementById('web_lookup_enabled');
    if (webLookup) webLookup.checked = data.web_lookup_enabled !== false;
  }

  function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
  }

  function getVal(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
  }

  async function loadSettings() {
    const res = await fetch(apiBase() + '/api/settings.php', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Unable to load settings.');
    fillForm(json.data);
  }

  async function saveSettings(e) {
    e.preventDefault();
    const payload = {
      action: 'save',
      ai_provider: getVal('ai_provider'),
      gemini_model: getVal('gemini_model'),
      gemini_fallback_models: getVal('gemini_fallback_models'),
      gemini_api_key: getVal('gemini_api_key'),
      agnes_model: getVal('agnes_model'),
      agnes_fallback_models: getVal('agnes_fallback_models'),
      agnes_base_url: getVal('agnes_base_url'),
      agnes_api_key: getVal('agnes_api_key'),
      request_timeout: Number(getVal('request_timeout') || 45),
      db_enabled: !!(document.getElementById('db_enabled') && document.getElementById('db_enabled').checked),
      web_lookup_enabled: !!(document.getElementById('web_lookup_enabled') && document.getElementById('web_lookup_enabled').checked),
    };

    const res = await fetch(apiBase() + '/api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json.ok) {
      showToast(json.error || 'Save failed.');
      return;
    }

    // Clear password fields after save
    setVal('gemini_api_key', '');
    setVal('agnes_api_key', '');
    fillForm(json.data);
    showToast('Settings saved.');
  }

  function renderTests(data) {
    const box = document.getElementById('testResults');
    if (!box) return;
    const providers = [
      { key: 'gemini', label: 'Gemini' },
      { key: 'agnes', label: 'Agnes' },
    ];
    box.innerHTML = providers.map((p) => {
      const item = data[p.key] || { ok: false, message: 'Unknown' };
      const cls = item.ok ? 'ok' : 'fail';
      const mark = item.ok ? '✓' : '✗';
      return `<div class="test-item"><strong>${p.label}</strong><span class="${cls}">${mark} ${escapeHtml(item.message || '')}</span></div>`;
    }).join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async function testConnection() {
    const box = document.getElementById('testResults');
    if (box) box.innerHTML = '<div class="test-item">Testing...</div>';
    const res = await fetch(apiBase() + '/api/settings.php?action=test', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!json.ok) {
      showToast(json.error || 'Test failed.');
      return;
    }
    renderTests(json.data);
  }

  function bindKeyToggle(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    if (!input || !btn) return;
    btn.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? 'Show' : 'Hide';
    });
  }

  async function loadQr() {
    const img = document.getElementById('qrImage');
    const urlEl = document.getElementById('qrUrl');
    if (!img) return;

    const res = await fetch(apiBase() + '/api/settings.php?action=qr_url', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!json.ok) return;

    let url = json.data.url || '';
    // Prefer HTTPS for phone QR when possible
    if (url.startsWith('http://') && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      url = 'https://' + url.slice('http://'.length);
    }
    if (urlEl) urlEl.textContent = url;
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url);
    img.alt = 'QR code for ' + url;
  }

  document.addEventListener('DOMContentLoaded', async () => {
    bindKeyToggle('gemini_api_key', 'toggleGeminiKey');
    bindKeyToggle('agnes_api_key', 'toggleAgnesKey');

    const form = document.getElementById('settingsForm');
    if (form) form.addEventListener('submit', (e) => {
      saveSettings(e).catch((err) => showToast(err.message || 'Save failed.'));
    });

    const testBtn = document.getElementById('testBtn');
    if (testBtn) testBtn.addEventListener('click', () => {
      testConnection().catch((err) => showToast(err.message || 'Test failed.'));
    });

    try {
      await loadSettings();
      await loadQr();
    } catch (err) {
      showToast(err.message || 'Unable to load settings.');
    }

    initClientPrefs();
  });

  function bindCheck(id, key) {
    const el = document.getElementById(id);
    if (!el || !window.ClientPrefs) return;
    el.addEventListener('change', () => ClientPrefs.set(key, el.checked));
  }

  function fillClientPrefs() {
    const resolution = document.getElementById('prefResolution');
    if (resolution) resolution.value = ClientPrefs.get('cameraResolution') || 'auto';
    ['prefAutofocus:autofocus', 'prefTapFocus:tapToFocus', 'prefHaptic:haptic', 'prefVoiceEnabled:voiceEnabled', 'prefAutoSpeak:autoSpeak'].forEach((pair) => {
      const parts = pair.split(':');
      const el = document.getElementById(parts[0]);
      if (el) el.checked = !!ClientPrefs.get(parts[1]);
    });
    const volume = document.getElementById('prefVolume');
    const volumeLabel = document.getElementById('volumeLabel');
    if (volume) volume.value = String(ClientPrefs.get('voiceVolume'));
    if (volumeLabel) volumeLabel.textContent = (ClientPrefs.get('voiceVolume') || 100) + '%';
    const torchMode = document.getElementById('prefTorchMode');
    if (torchMode) torchMode.value = ClientPrefs.get('torchMode') || 'auto';
    const voiceSelect = document.getElementById('prefVoice');
    if (voiceSelect) voiceSelect.value = ClientPrefs.get('voiceURI') || '';
  }

  function initClientPrefs() {
    if (!window.ClientPrefs || initClientPrefs.bound) {
      if (window.ClientPrefs) fillClientPrefs();
      return;
    }
    initClientPrefs.bound = true;
    fillClientPrefs();

    const resolution = document.getElementById('prefResolution');
    if (resolution) resolution.addEventListener('change', () => ClientPrefs.set('cameraResolution', resolution.value));
    bindCheck('prefAutofocus', 'autofocus');
    bindCheck('prefTapFocus', 'tapToFocus');
    bindCheck('prefHaptic', 'haptic');
    const torchMode = document.getElementById('prefTorchMode');
    if (torchMode) torchMode.addEventListener('change', () => ClientPrefs.set('torchMode', torchMode.value));
    bindCheck('prefVoiceEnabled', 'voiceEnabled');
    bindCheck('prefAutoSpeak', 'autoSpeak');

    const hapticNote = document.getElementById('hapticNote');
    if (hapticNote && !('vibrate' in navigator)) {
      hapticNote.textContent = 'Haptic feedback is not supported on this device.';
    }

    const volume = document.getElementById('prefVolume');
    const volumeLabel = document.getElementById('volumeLabel');
    if (volume) {
      volume.addEventListener('input', () => {
        ClientPrefs.set('voiceVolume', Number(volume.value));
        if (volumeLabel) volumeLabel.textContent = volume.value + '%';
      });
    }

    const voiceNote = document.getElementById('voiceNote');
    const voiceSelect = document.getElementById('prefVoice');
    if (!window.ScannerVoice || !ScannerVoice.isSupported()) {
      if (voiceNote) voiceNote.textContent = 'Voice narration is not supported by this browser.';
    } else if (voiceSelect) {
      ScannerVoice.whenVoicesReady((voices) => {
        const saved = ClientPrefs.get('voiceURI') || '';
        const groups = {};
        voices.forEach((v) => {
          const key = v.group || 'Other';
          if (!groups[key]) groups[key] = [];
          groups[key].push(v);
        });
        let html = '<option value="">自动（跟随识别语言）</option>';
        Object.keys(groups).forEach((key) => {
          html += '<optgroup label="' + escapeHtml(key) + '">';
          groups[key].forEach((v) => {
            html += '<option value="' + escapeHtml(v.uri) + '">' + escapeHtml(v.label) + '</option>';
          });
          html += '</optgroup>';
        });
        voiceSelect.innerHTML = html;
        voiceSelect.value = saved;
        if (voiceNote) {
          voiceNote.textContent = voices.length
            ? ('已加载 ' + voices.length + ' 个系统语音。若没有中文或其他语言，请在手机「文字转语音」设置里下载对应语言包。')
            : '正在读取系统语音，请稍候再打开此列表。';
        }
      });
      voiceSelect.addEventListener('change', () => ClientPrefs.set('voiceURI', voiceSelect.value));
    }

    const testVoice = document.getElementById('testVoiceBtn');
    if (testVoice) {
      testVoice.addEventListener('click', () => {
        const result = ScannerVoice.test();
        if (!result.ok) showToast(result.error || 'Voice unavailable.');
      });
    }
    const stopVoice = document.getElementById('stopVoiceBtn');
    if (stopVoice && window.ScannerVoice) {
      stopVoice.addEventListener('click', () => ScannerVoice.stop());
    }
    const resetBtn = document.getElementById('resetClientPrefs');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        ClientPrefs.reset();
        fillClientPrefs();
        showToast('Camera and voice settings reset.');
      });
    }
  }
})();
