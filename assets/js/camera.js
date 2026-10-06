/**
 * Camera preview: resolution fallback, switching, torch, autofocus, capture.
 */
(function (global) {
  'use strict';

  const PRESETS = {
    standard: { width: 640, height: 480 },
    '720p': { width: 1280, height: 720 },
    '1080p': { width: 1920, height: 1080 },
    high: { width: 2560, height: 1440 },
  };

  const Camera = {
    stream: null,
    video: null,
    usingEnvironment: true,
    facingMode: 'environment',
    deviceId: '',
    devices: [],
    torchOn: false,
    supportsTorchFlag: false,
    torchTimer: null,
    torchBusy: false,
    captureLock: false,
    lastProbe: 0,
    brightnessCanvas: null,
    onTorchChange: null,

    async start(videoEl, options) {
      this.video = videoEl;
      const opts = options || {};
      const facing = opts.facingMode || '';
      const requestedId = opts.deviceId || '';
      const strict = !!opts.strict;

      this.stopBrightnessWatch();
      this.torchBusy = true;
      this.torchOn = false;
      this.stopTracks();
      if (videoEl) {
        try { videoEl.pause(); } catch (_) { /* ignore */ }
        videoEl.srcObject = null;
      }

      if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        this.torchBusy = false;
        throw new Error('Please use HTTPS to access the camera.');
      }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        this.torchBusy = false;
        throw new Error('Camera is unavailable.');
      }

      await new Promise((resolve) => window.setTimeout(resolve, 80));

      const attempts = this.buildAttempts(requestedId, facing, strict);
      let lastError = null;
      for (let i = 0; i < attempts.length; i += 1) {
        try {
          this.stream = await navigator.mediaDevices.getUserMedia(attempts[i]);
          videoEl.srcObject = this.stream;
          videoEl.setAttribute('playsinline', 'true');
          videoEl.muted = true;
          await videoEl.play();
          this.syncDeviceId();
          if (facing === 'user' || facing === 'environment') this.facingMode = facing;
          this.usingEnvironment = this.facingMode !== 'user';
          await this.refreshDevices();
          await this.applyAutofocus();
          this.supportsTorchFlag = this.canTorch();
          this.torchBusy = false;
          await this.applyTorchMode();
          if (this.facingMode !== 'user' && this.canTorch()) this.watchBrightness();
          else this.stopBrightnessWatch();
          return true;
        } catch (err) {
          lastError = err;
          this.stopTracks();
        }
      }

      this.torchBusy = false;
      const name = lastError && lastError.name ? lastError.name : '';
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        throw new Error('Camera permission was denied. Please allow camera access.');
      }
      if (facing === 'user') {
        throw new Error('Front camera is not available on this device.');
      }
      throw new Error('Camera is unavailable.');
    },

    buildAttempts(deviceId, facing, strict) {
      const mode = String(ClientPrefs.get('cameraResolution') || 'auto');
      const order = mode === 'auto'
        ? ['720p', '1080p', 'standard', 'high']
        : [mode, '720p', '1080p', 'standard'];
      const attempts = [];
      const seen = {};
      const face = facing || '';

      order.forEach((key) => {
        const preset = PRESETS[key];
        if (!preset || seen[key]) return;
        seen[key] = true;
        attempts.push(this.constraints(preset, deviceId, face));
      });

      if (deviceId) {
        attempts.push({ video: { deviceId: { exact: deviceId } }, audio: false });
      }
      if (face) {
        attempts.push({ video: { facingMode: { ideal: face } }, audio: false });
        attempts.push({ video: { facingMode: face }, audio: false });
      }
      if (!strict && face !== 'user') {
        attempts.push({ video: true, audio: false });
      }
      return attempts;
    },

    constraints(preset, deviceId, facing) {
      const video = {
        width: { ideal: preset.width },
        height: { ideal: preset.height },
      };
      if (deviceId) video.deviceId = { exact: deviceId };
      else video.facingMode = { ideal: facing || 'environment' };
      return { video: video, audio: false };
    },

    stopTracks() {
      if (this.stream) {
        this.stream.getTracks().forEach((track) => track.stop());
        this.stream = null;
      }
    },

    stop() {
      this.stopBrightnessWatch();
      this.torchOn = false;
      this.stopTracks();
      if (this.video) this.video.srcObject = null;
    },

    track() {
      if (!this.stream) return null;
      const tracks = this.stream.getVideoTracks();
      return tracks.length ? tracks[0] : null;
    },

    syncDeviceId() {
      const track = this.track();
      if (!track || typeof track.getSettings !== 'function') return;
      const settings = track.getSettings();
      if (settings && settings.deviceId) this.deviceId = settings.deviceId;
    },

    guessEnvironment() {
      const track = this.track();
      if (!track || typeof track.getSettings !== 'function') return true;
      const facing = track.getSettings().facingMode;
      if (!facing) return this.usingEnvironment;
      return facing === 'environment';
    },

    async refreshDevices() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        this.devices = [];
        return this.devices;
      }
      const all = await navigator.mediaDevices.enumerateDevices();
      this.devices = all.filter((d) => d.kind === 'videoinput');
      return this.devices;
    },

    cameraOptions() {
      return this.devices.map((d, index) => ({
        deviceId: d.deviceId,
        label: d.label || ('Camera ' + (index + 1)),
      }));
    },

    deviceIdForFacing(facing) {
      const front = /front|user|face|selfie|前置|前摄/i;
      const back = /back|rear|environment|后置|后摄/i;
      const list = this.devices || [];
      if (facing === 'user') {
        const hit = list.find((d) => front.test(d.label || ''));
        return hit ? hit.deviceId : '';
      }
      const hit = list.find((d) => back.test(d.label || '') && !front.test(d.label || ''));
      return hit ? hit.deviceId : '';
    },

    async switchNext(videoEl) {
      const video = videoEl || this.video;
      await this.refreshDevices();
      const nextFacing = this.facingMode === 'user' ? 'environment' : 'user';
      const previousFacing = this.facingMode;
      const labeledId = this.deviceIdForFacing(nextFacing);

      try {
        if (labeledId && labeledId !== this.deviceId) {
          try {
            await this.start(video, { deviceId: labeledId, facingMode: nextFacing, strict: true });
            return true;
          } catch (_) {
            this.facingMode = previousFacing;
          }
        }
        await this.start(video, { facingMode: nextFacing, strict: nextFacing === 'user' });
        return true;
      } catch (err) {
        try {
          await this.start(video, { facingMode: previousFacing });
        } catch (_) { /* keep the original error */ }
        throw err;
      }
    },

    canTorch() {
      const track = this.track();
      if (!track || typeof track.getCapabilities !== 'function') return false;
      const caps = track.getCapabilities();
      return !!(caps && caps.torch);
    },

    torchMode() {
      const saved = ClientPrefs.get('torchMode');
      if (saved === 'on' || saved === 'off' || saved === 'auto') return saved;
      return 'auto';
    },

    async applyTorchMode() {
      if (!this.canTorch()) {
        this.torchOn = false;
        return { ok: false, error: 'Flashlight is not supported on this device.' };
      }
      const mode = this.torchMode();
      if (mode === 'on') return this.setTorch(true);
      if (mode === 'off') return this.setTorch(false);
      return this.autoAdjustTorch();
    },

    async cycleTorchMode() {
      if (!this.canTorch()) {
        return { ok: false, error: 'Flashlight is not supported on this device.' };
      }
      const order = ['auto', 'on', 'off'];
      const next = order[(order.indexOf(this.torchMode()) + 1) % order.length];
      ClientPrefs.set('torchMode', next);
      const applied = await this.applyTorchMode();
      return { ok: true, mode: next, on: this.torchOn, error: applied && applied.error };
    },

    watchBrightness() {
      this.stopBrightnessWatch();
      this.torchTimer = window.setInterval(() => {
        this.autoAdjustTorch();
      }, 700);
    },

    stopBrightnessWatch() {
      if (this.torchTimer) {
        window.clearInterval(this.torchTimer);
        this.torchTimer = null;
      }
    },

    /**
     * Auto mode follows the room, like a camera light.
     * Dark preview turns the light on. While it is on, the lit picture is not
     * trusted: the light is dropped for a short probe so a bright place turns it off.
     */
    async autoAdjustTorch() {
      if (this.torchMode() !== 'auto' || !this.canTorch() || this.torchBusy || this.captureLock) {
        return { ok: true, on: this.torchOn };
      }
      if (!this.torchOn) {
        const level = this.sampleBrightness();
        if (level !== null && level < 50) {
          const applied = await this.setTorch(true);
          this.lastProbe = Date.now();
          this.notifyTorch();
          return applied;
        }
        return { ok: true, on: false };
      }

      if (Date.now() - this.lastProbe < 1400) return { ok: true, on: true };
      this.lastProbe = Date.now();
      this.torchBusy = true;
      try {
        await this.setTorch(false);
        await this.waitForFreshFrame();
        const ambient = this.sampleBrightness();
        if (ambient === null || ambient < 62) {
          await this.setTorch(true);
        }
        this.notifyTorch();
        return { ok: true, on: this.torchOn };
      } finally {
        this.torchBusy = false;
      }
    },

    waitForFreshFrame() {
      const video = this.video;
      const frame = () => new Promise((resolve) => {
        if (video && typeof video.requestVideoFrameCallback === 'function') {
          video.requestVideoFrameCallback(() => resolve());
          return;
        }
        window.setTimeout(resolve, 50);
      });
      return frame().then(frame).then(() => new Promise((resolve) => window.setTimeout(resolve, 180)));
    },

    notifyTorch() {
      if (typeof this.onTorchChange === 'function') this.onTorchChange();
    },

    sampleBrightness() {
      const video = this.video;
      if (!video || !video.videoWidth) return null;
      if (!this.brightnessCanvas) {
        this.brightnessCanvas = document.createElement('canvas');
        this.brightnessCanvas.width = 32;
        this.brightnessCanvas.height = 32;
      }
      const canvas = this.brightnessCanvas;
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      if (!ctx) return null;
      try {
        ctx.drawImage(video, 0, 0, 32, 32);
        const pixels = ctx.getImageData(0, 0, 32, 32).data;
        let total = 0;
        const count = pixels.length / 4;
        for (let i = 0; i < pixels.length; i += 16) {
          total += (pixels[i] * 0.2126) + (pixels[i + 1] * 0.7152) + (pixels[i + 2] * 0.0722);
        }
        return total / (count / 4);
      } catch (_) {
        return null;
      }
    },

    async setTorch(enabled) {
      const track = this.track();
      if (!track || !this.canTorch()) {
        this.torchOn = false;
        this.supportsTorchFlag = false;
        return { ok: false, error: 'Flashlight is not supported on this device.' };
      }
      try {
        await track.applyConstraints({ advanced: [{ torch: !!enabled }] });
        this.torchOn = !!enabled;
        return { ok: true, on: this.torchOn };
      } catch (_) {
        return { ok: false, error: 'Flashlight is not supported on this device.' };
      }
    },

    async applyAutofocus() {
      if (!ClientPrefs.get('autofocus')) return false;
      const track = this.track();
      if (!track || typeof track.getCapabilities !== 'function') return false;
      const caps = track.getCapabilities();
      if (!caps.focusMode || caps.focusMode.indexOf('continuous') === -1) return false;
      try {
        await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        return true;
      } catch (_) {
        return false;
      }
    },

    async focusAt(clientX, clientY, stageEl) {
      const track = this.track();
      if (!track || typeof track.getCapabilities !== 'function') return false;
      const caps = track.getCapabilities();
      if (!caps.focusMode) return false;

      const rect = stageEl.getBoundingClientRect();
      const x = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
      const y = Math.min(1, Math.max(0, (clientY - rect.top) / rect.height));

      try {
        if (caps.focusMode.indexOf('single-shot') !== -1) {
          await track.applyConstraints({ advanced: [{ focusMode: 'single-shot', pointsOfInterest: [{ x: x, y: y }] }] });
        } else if (caps.focusMode.indexOf('manual') !== -1) {
          await track.applyConstraints({ advanced: [{ focusMode: 'manual', pointsOfInterest: [{ x: x, y: y }] }] });
        } else if (caps.focusMode.indexOf('continuous') !== -1) {
          await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        } else {
          return false;
        }
        return true;
      } catch (_) {
        try {
          await this.applyAutofocus();
        } catch (__) { /* ignore */ }
        return false;
      }
    },

    /**
     * Downscale before AI upload so HD preview does not send a huge payload.
     */
    captureFrame(maxDim, quality) {
      const limit = maxDim || 1280;
      const jpeg = typeof quality === 'number' ? quality : 0.7;
      const video = this.video;
      if (!video || !video.videoWidth) {
        throw new Error('Camera feed is not ready.');
      }

      const scale = Math.min(1, limit / Math.max(video.videoWidth, video.videoHeight));
      const tw = Math.max(1, Math.round(video.videoWidth * scale));
      const th = Math.max(1, Math.round(video.videoHeight * scale));
      const canvas = document.createElement('canvas');
      canvas.width = tw;
      canvas.height = th;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Unable to capture image.');
      ctx.drawImage(video, 0, 0, tw, th);
      return canvas.toDataURL('image/jpeg', jpeg);
    },

    getVideoSize() {
      if (!this.video) return { width: 0, height: 0 };
      return { width: this.video.videoWidth || 0, height: this.video.videoHeight || 0 };
    },
  };

  window.addEventListener('pagehide', () => Camera.stop());

  global.ScannerCamera = Camera;
})(window);
