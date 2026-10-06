/**
 * Browser-local scanner preferences (camera, voice, haptic).
 * Separate from server AI config so API keys stay on the server.
 */
(function (global) {
  'use strict';

  const KEY = 'aiArClientPrefs';
  const DEFAULTS = {
    cameraResolution: 'auto',
    autofocus: true,
    tapToFocus: true,
    haptic: true,
    torchMode: 'auto',
    voiceEnabled: true,
    autoSpeak: true,
    voiceVolume: 100,
    voiceURI: '',
    resultLang: 'en',
  };

  function read() {
    try {
      const raw = localStorage.getItem(KEY);
      const parsed = raw ? JSON.parse(raw) : {};
      return Object.assign({}, DEFAULTS, parsed && typeof parsed === 'object' ? parsed : {});
    } catch (_) {
      return Object.assign({}, DEFAULTS);
    }
  }

  function write(next) {
    localStorage.setItem(KEY, JSON.stringify(next));
  }

  const ClientPrefs = {
    defaults: DEFAULTS,
    all: read,
    get(key) {
      return read()[key];
    },
    set(key, value) {
      const next = read();
      next[key] = value;
      write(next);
      return next;
    },
    reset() {
      write(Object.assign({}, DEFAULTS));
      return read();
    },
  };

  global.ClientPrefs = ClientPrefs;
})(window);
