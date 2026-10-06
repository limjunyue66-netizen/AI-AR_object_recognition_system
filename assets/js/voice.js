/**
 * Voice narration via the browser Speech Synthesis API.
 */
(function (global) {
  'use strict';

  const TEST_LINE = 'This is a voice test for the AI Object Recognition System.';

  const Voice = {
    latest: null,
    lastLine: '',
    lastLang: '',
    supported: typeof window !== 'undefined' && 'speechSynthesis' in window,

    isSupported() {
      return this.supported;
    },

    volume() {
      const n = Number(ClientPrefs.get('voiceVolume'));
      if (!Number.isFinite(n)) return 1;
      return Math.max(0, Math.min(1, n / 100));
    },

    stop() {
      if (!this.supported) return;
      try {
        window.speechSynthesis.cancel();
      } catch (_) { /* ignore */ }
    },

    remember(result) {
      this.latest = result || null;
    },

    /**
     * Natural sentence from a recognition result (not raw JSON).
     */
    buildSummary(result) {
      if (!result || typeof result !== 'object') return '';
      const product = clean(result.productName);
      const maker = clean(result.manufacturer);
      const objectLabel = clean(result.objectLabel);
      const description = firstSentence(clean(result.description));
      const name = product || objectLabel;
      if (!name) return '';

      const chinese = /[\u4e00-\u9fff]/.test(name + maker + description);
      if (chinese) {
        let line = '这是';
        if (maker && maker !== '未知' && name.indexOf(maker) === -1) line += maker;
        line += name + '。';
        if (description) line += description;
        return line;
      }

      let line = 'This is ';
      if (maker && maker.toLowerCase() !== 'unknown' && name.toLowerCase().indexOf(maker.toLowerCase()) === -1) {
        line += maker + ' ';
      }
      line += name + '.';
      if (description) line += ' ' + description;
      return line.replace(/\s+/g, ' ').trim();
    },

    speakText(text, langHint) {
      if (!this.supported) {
        return { ok: false, error: 'Voice narration is not supported by this browser.' };
      }
      if (!ClientPrefs.get('voiceEnabled')) {
        return { ok: false, error: 'Voice narration is turned off.' };
      }
      const line = clean(text);
      if (!line) return { ok: false, error: 'Nothing to speak.' };

      this.stop();
      const utter = new SpeechSynthesisUtterance(line);
      utter.volume = this.volume();
      utter.rate = 1;
      utter.pitch = 1;

      const voice = pickVoice(line, langHint);
      if (voice) {
        utter.voice = voice;
        utter.lang = normalizeLang(langHint || voice.lang) || guessLang(line);
      } else {
        utter.lang = normalizeLang(langHint) || guessLang(line);
      }

      this.lastLine = line;
      this.lastLang = utter.lang || langHint || '';
      // Chrome drops speech if speak() runs in the same turn as cancel().
      window.setTimeout(() => {
        try {
          window.speechSynthesis.resume();
          window.speechSynthesis.speak(utter);
        } catch (_) { /* ignore */ }
      }, 40);
      return { ok: true };
    },

    speakResult(result) {
      this.remember(result);
      return this.speakText(this.buildSummary(result));
    },

    speakLatest() {
      if (!this.latest) return { ok: false, error: 'Scan an object first.' };
      return this.speakResult(this.latest);
    },

    replay() {
      if (!this.lastLine) {
        return { ok: false, error: 'Nothing to replay yet. Tap Speak first.' };
      }
      return this.speakText(this.lastLine, this.lastLang);
    },

    test() {
      const saved = findSavedVoice();
      const lang = saved && saved.lang ? saved.lang : 'en';
      return this.speakText(sampleFor(lang), lang);
    },

    /**
     * Speak whatever is currently shown on the result card.
     */
    speakVisibleCard(cardEl, langHint) {
      if (!cardEl) return this.speakLatest();
      const field = (name) => {
        const el = cardEl.querySelector('[data-field="' + name + '"]');
        return el ? clean(el.textContent) : '';
      };
      const snapshot = {
        productName: field('productName'),
        manufacturer: field('manufacturer'),
        objectLabel: (this.latest && this.latest.objectLabel) || '',
        description: field('description'),
      };
      this.remember(Object.assign({}, this.latest || {}, snapshot));
      return this.speakText(this.buildSummary(snapshot), langHint);
    },

    listVoices() {
      if (!this.supported) return [];
      const voices = window.speechSynthesis.getVoices() || [];
      return voices.map((v) => {
        const lang = normalizeLang(v.lang);
        const languageName = languageLabel(lang);
        return {
          uri: v.voiceURI,
          name: v.name,
          lang: lang,
          group: languageName + (lang ? ' · ' + lang : ''),
          label: (v.name || 'Voice') + (lang ? ' — ' + lang : ''),
        };
      }).sort((a, b) => {
        const ga = String(a.group).localeCompare(String(b.group));
        if (ga !== 0) return ga;
        return String(a.label).localeCompare(String(b.label));
      });
    },

    whenVoicesReady(callback) {
      if (!this.supported) return;
      const publish = () => callback(this.listVoices());
      publish();
      window.speechSynthesis.addEventListener('voiceschanged', publish);
    },
  };

  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function firstSentence(text) {
    if (!text || text === '—') return '';
    const match = text.match(/^.{1,180}?[.。!！?？]/);
    return match ? match[0] : text.slice(0, 180);
  }

  function normalizeLang(lang) {
    return String(lang || '').trim().replace(/_/g, '-');
  }

  function languageLabel(lang) {
    const code = normalizeLang(lang);
    if (!code) return 'Other';
    try {
      const names = new Intl.DisplayNames(['zh-CN', 'en'], { type: 'language' });
      return names.of(code) || names.of(code.split('-')[0]) || code;
    } catch (_) {
      return code;
    }
  }

  function guessLang(text) {
    if (/[\u4e00-\u9fff]/.test(text)) return 'zh-CN';
    if (/[\u3040-\u30ff]/.test(text)) return 'ja-JP';
    if (/[\uac00-\ud7af]/.test(text)) return 'ko-KR';
    if (/[\u0e00-\u0e7f]/.test(text)) return 'th-TH';
    if (/[\u0600-\u06ff]/.test(text)) return 'ar-SA';
    return 'en-US';
  }

  function sampleFor(lang) {
    const code = normalizeLang(lang).toLowerCase().split('-')[0];
    const samples = {
      zh: '这是人工智能物体识别系统的语音测试。',
      en: TEST_LINE,
      ms: 'Ini ialah ujian suara untuk sistem pengecaman objek AI.',
      id: 'Ini adalah tes suara untuk sistem pengenalan objek AI.',
      ja: 'これはAI物体認識システムの音声テストです。',
      ko: '이것은 AI 물체 인식 시스템의 음성 테스트입니다.',
      th: 'นี่คือการทดสอบเสียงสำหรับระบบจดจำวัตถุ AI',
      vi: 'Đây là bài kiểm tra giọng nói cho hệ thống nhận dạng đối tượng AI.',
      hi: 'यह एआई वस्तु पहचान प्रणाली के लिए आवाज़ परीक्षण है।',
      ta: 'இது AI பொருள் அறிதல் அமைப்புக்கான குரல் சோதனை.',
      ar: 'هذا اختبار صوتي لنظام التعرف على الأشياء بالذكاء الاصطناعي.',
      fr: "Ceci est un test vocal pour le système de reconnaissance d'objets IA.",
      de: 'Dies ist ein Sprachtest für das KI-Objekterkennungssystem.',
      es: 'Esta es una prueba de voz para el sistema de reconocimiento de objetos con IA.',
      pt: 'Este é um teste de voz para o sistema de reconhecimento de objetos com IA.',
      ru: 'Это голосовой тест системы распознавания объектов на базе ИИ.',
      it: 'Questo è un test vocale per il sistema di riconoscimento oggetti IA.',
      nl: 'Dit is een stemtest voor het AI-objectherkenningssysteem.',
      tr: 'Bu, yapay zeka nesne tanıma sistemi için bir ses testidir.',
      pl: 'To jest test głosu systemu rozpoznawania obiektów AI.',
    };
    return samples[code] || TEST_LINE;
  }

  function findSavedVoice() {
    const saved = ClientPrefs.get('voiceURI');
    if (!saved) return null;
    const voices = window.speechSynthesis.getVoices() || [];
    return voices.find((v) => v.voiceURI === saved) || null;
  }

  function pickVoice(text, langHint) {
    const voices = window.speechSynthesis.getVoices() || [];
    const wanted = normalizeLang(langHint || guessLang(text)).split('-')[0].toLowerCase();
    const matched = voices.find((v) => normalizeLang(v.lang).toLowerCase().indexOf(wanted) === 0);
    if (matched) return matched;
    const saved = findSavedVoice();
    if (saved) return saved;
    return voices.find((v) => /^en/i.test(normalizeLang(v.lang))) || null;
  }

  window.addEventListener('pagehide', () => Voice.stop());

  global.ScannerVoice = Voice;
})(window);
