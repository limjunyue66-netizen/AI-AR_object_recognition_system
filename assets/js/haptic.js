/**
 * Optional vibration. Silent when unsupported or turned off.
 */
(function (global) {
  'use strict';

  function pulse(pattern) {
    if (!ClientPrefs.get('haptic')) return;
    if (!navigator.vibrate) return;
    try {
      navigator.vibrate(pattern);
    } catch (_) { /* ignore */ }
  }

  global.ScannerHaptic = {
    supported: typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function',
    focus() { pulse(30); },
    success() { pulse([30, 50, 30]); },
    error() { pulse([40, 40, 80]); },
  };
})(window);
