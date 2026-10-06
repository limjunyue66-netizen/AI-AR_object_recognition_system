/**
 * AR bounding box + fixed TOP-LEFT result panel.
 * English / Chinese toggle for item details.
 */
(function (global) {
  'use strict';

  const LANGUAGES = (window.APP_LANGUAGES || [{ code: 'en', name: 'English' }]).slice().sort(function (a, b) {
    return a.name.localeCompare(b.name);
  });

  const UI = {
    en: { expand: 'Show details', collapse: 'Hide details', manufacturer: 'Manufacturer', specification: 'Specification', description: 'Description', confidence: 'Confidence', provider: 'Provider', unknown: 'Unknown object', unknownBrand: 'Unknown' },
    'zh-CN': { expand: '展开详情', collapse: '收起详情', manufacturer: '制造商', specification: '规格', description: '描述', confidence: '置信度', provider: '识别服务', unknown: '未知物品', unknownBrand: '未知' },
    ms: { expand: 'Tunjuk butiran', collapse: 'Sembunyi butiran', manufacturer: 'Pengeluar', specification: 'Spesifikasi', description: 'Penerangan', confidence: 'Keyakinan', provider: 'Penyedia', unknown: 'Objek tidak diketahui', unknownBrand: 'Tidak diketahui' },
    id: { expand: 'Tampilkan detail', collapse: 'Sembunyikan detail', manufacturer: 'Produsen', specification: 'Spesifikasi', description: 'Deskripsi', confidence: 'Keyakinan', provider: 'Penyedia', unknown: 'Objek tidak dikenal', unknownBrand: 'Tidak diketahui' },
    ja: { expand: '詳細を表示', collapse: '詳細を隠す', manufacturer: 'メーカー', specification: '仕様', description: '説明', confidence: '信頼度', provider: '認識元', unknown: '不明な物体', unknownBrand: '不明' },
    ko: { expand: '자세히 보기', collapse: '자세히 숨기기', manufacturer: '제조사', specification: '사양', description: '설명', confidence: '신뢰도', provider: '인식 서비스', unknown: '알 수 없는 물체', unknownBrand: '알 수 없음' },
    th: { expand: 'แสดงรายละเอียด', collapse: 'ซ่อนรายละเอียด', manufacturer: 'ผู้ผลิต', specification: 'ข้อมูลจำเพาะ', description: 'คำอธิบาย', confidence: 'ความมั่นใจ', provider: 'ผู้ให้บริการ', unknown: 'วัตถุที่ไม่รู้จัก', unknownBrand: 'ไม่ทราบ' },
    vi: { expand: 'Xem chi tiết', collapse: 'Ẩn chi tiết', manufacturer: 'Nhà sản xuất', specification: 'Thông số', description: 'Mô tả', confidence: 'Độ tin cậy', provider: 'Nhà cung cấp', unknown: 'Vật thể không rõ', unknownBrand: 'Không rõ' },
    hi: { expand: 'विवरण दिखाएँ', collapse: 'विवरण छिपाएँ', manufacturer: 'निर्माता', specification: 'विशेषता', description: 'विवरण', confidence: 'विश्वास', provider: 'प्रदाता', unknown: 'अज्ञात वस्तु', unknownBrand: 'अज्ञात' },
    ta: { expand: 'விவரங்களை காட்டு', collapse: 'விவரங்களை மறை', manufacturer: 'உற்பத்தியாளர்', specification: 'விவரம்', description: 'விளக்கம்', confidence: 'நம்பிக்கை', provider: 'வழங்குநர்', unknown: 'தெரியாத பொருள்', unknownBrand: 'தெரியாத' },
    ar: { expand: 'عرض التفاصيل', collapse: 'إخفاء التفاصيل', manufacturer: 'الشركة', specification: 'المواصفات', description: 'الوصف', confidence: 'الثقة', provider: 'المصدر', unknown: 'جسم غير معروف', unknownBrand: 'غير معروف' },
    fr: { expand: 'Afficher les détails', collapse: 'Masquer les détails', manufacturer: 'Fabricant', specification: 'Spécification', description: 'Description', confidence: 'Confiance', provider: 'Fournisseur', unknown: 'Objet inconnu', unknownBrand: 'Inconnu' },
    de: { expand: 'Details zeigen', collapse: 'Details ausblenden', manufacturer: 'Hersteller', specification: 'Spezifikation', description: 'Beschreibung', confidence: 'Vertrauen', provider: 'Anbieter', unknown: 'Unbekanntes Objekt', unknownBrand: 'Unbekannt' },
    es: { expand: 'Ver detalles', collapse: 'Ocultar detalles', manufacturer: 'Fabricante', specification: 'Especificación', description: 'Descripción', confidence: 'Confianza', provider: 'Proveedor', unknown: 'Objeto desconocido', unknownBrand: 'Desconocido' },
    pt: { expand: 'Mostrar detalhes', collapse: 'Ocultar detalhes', manufacturer: 'Fabricante', specification: 'Especificação', description: 'Descrição', confidence: 'Confiança', provider: 'Provedor', unknown: 'Objeto desconhecido', unknownBrand: 'Desconhecido' },
    ru: { expand: 'Показать детали', collapse: 'Скрыть детали', manufacturer: 'Производитель', specification: 'Характеристики', description: 'Описание', confidence: 'Доверие', provider: 'Источник', unknown: 'Неизвестный объект', unknownBrand: 'Неизвестно' },
    it: { expand: 'Mostra dettagli', collapse: 'Nascondi dettagli', manufacturer: 'Produttore', specification: 'Specifiche', description: 'Descrizione', confidence: 'Affidabilità', provider: 'Fonte', unknown: 'Oggetto sconosciuto', unknownBrand: 'Sconosciuto' },
    nl: { expand: 'Details tonen', collapse: 'Details verbergen', manufacturer: 'Fabrikant', specification: 'Specificatie', description: 'Beschrijving', confidence: 'Zekerheid', provider: 'Aanbieder', unknown: 'Onbekend object', unknownBrand: 'Onbekend' },
    tr: { expand: 'Ayrıntıları göster', collapse: 'Ayrıntıları gizle', manufacturer: 'Üretici', specification: 'Özellikler', description: 'Açıklama', confidence: 'Güven', provider: 'Sağlayıcı', unknown: 'Bilinmeyen nesne', unknownBrand: 'Bilinmiyor' },
    pl: { expand: 'Pokaż szczegóły', collapse: 'Ukryj szczegóły', manufacturer: 'Producent', specification: 'Specyfikacja', description: 'Opis', confidence: 'Pewność', provider: 'Źródło', unknown: 'Nieznany obiekt', unknownBrand: 'Nieznany' },
  };

  const ArOverlay = {
    boxEl: null,
    cardEl: null,
    stageEl: null,
    videoEl: null,
    translateBtn: null,
    expandBtn: null,
    bodyEl: null,
    data: null,
    lang: 'en',
    source: null,
    outputLang: 'en',
    cache: {},
    translating: false,
    expanded: true,

    init({ boxEl, cardEl, stageEl, videoEl }) {
      this.boxEl = boxEl;
      this.cardEl = cardEl;
      this.stageEl = stageEl;
      this.videoEl = videoEl;
      this.langButton = document.getElementById('langOpen');
      this.langSheet = document.getElementById('langSheet');
      this.langList = document.getElementById('langList');
      this.langSearch = document.getElementById('langSearch');
      this.expandBtn = document.getElementById('expandBtn');
      this.bodyEl = document.getElementById('resultBody');
      const saved = (window.ClientPrefs && ClientPrefs.get('resultLang')) || 'en';
      this.lang = LANGUAGES.some((item) => item.code === saved) ? saved : 'en';
      this.paintLanguageButton();
      this.renderLanguageList('');

      if (this.langButton) {
        this.langButton.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.openLanguageSheet();
        });
      }
      const closeBtn = document.getElementById('langClose');
      if (closeBtn) closeBtn.addEventListener('click', () => this.closeLanguageSheet());
      if (this.langSheet) {
        this.langSheet.addEventListener('click', (e) => {
          if (e.target === this.langSheet) this.closeLanguageSheet();
        });
      }
      if (this.langSearch) {
        this.langSearch.addEventListener('input', () => this.renderLanguageList(this.langSearch.value));
      }

      if (this.expandBtn) {
        this.expandBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.toggleExpand();
        });
      }

      window.addEventListener('resize', () => {
        if (this.data) this.positionBoxOnly();
      });
    },

    apiBase() {
      const meta = document.querySelector('meta[name="app-base"]');
      return meta ? meta.content.replace(/\/$/, '') : '';
    },

    pinTopLeft() {
      // Force top-left even if old CSS is cached
      this.cardEl.style.position = 'absolute';
      this.cardEl.style.top = 'calc(58px + env(safe-area-inset-top, 0px))';
      this.cardEl.style.left = '10px';
      this.cardEl.style.right = 'auto';
      this.cardEl.style.bottom = 'auto';
      this.cardEl.style.zIndex = '30';
      this.cardEl.style.transform = 'none';
    },

    clear() {
      this.data = null;
      this.cache = {};
      this.lang = 'en';
      this.translating = false;
      this.expanded = true;
      this.boxEl.classList.remove('visible');
      this.cardEl.classList.remove('is-visible');
      this.cardEl.hidden = true;
      if (this.bodyEl) {
        this.bodyEl.classList.remove('is-open');
        this.bodyEl.hidden = true;
      }
      if (this.expandBtn) {
        this.expandBtn.classList.remove('is-open');
        this.expandBtn.setAttribute('aria-expanded', 'false');
      }
      if (this.langButton) this.langButton.disabled = false;
      this.paintLanguageButton();
      this.applyStaticLabels('en');
    },

    mapBox(boundingBox) {
      const stage = this.stageEl.getBoundingClientRect();
      const vw = this.videoEl.videoWidth || 1;
      const vh = this.videoEl.videoHeight || 1;
      const sw = stage.width;
      const sh = stage.height;
      const videoRatio = vw / vh;
      const stageRatio = sw / sh;

      let drawW;
      let drawH;
      let offsetX;
      let offsetY;

      if (videoRatio > stageRatio) {
        drawH = sh;
        drawW = sh * videoRatio;
        offsetX = (sw - drawW) / 2;
        offsetY = 0;
      } else {
        drawW = sw;
        drawH = sw / videoRatio;
        offsetX = 0;
        offsetY = (sh - drawH) / 2;
      }

      return {
        left: Math.max(0, offsetX + (boundingBox.xmin / 1000) * drawW),
        top: Math.max(0, offsetY + (boundingBox.ymin / 1000) * drawH),
        width: Math.max(24, ((boundingBox.xmax - boundingBox.xmin) / 1000) * drawW),
        height: Math.max(24, ((boundingBox.ymax - boundingBox.ymin) / 1000) * drawH),
      };
    },

    positionBoxOnly() {
      if (!this.data) return;
      const box = this.data.boundingBox || { ymin: 200, xmin: 200, ymax: 800, xmax: 800 };
      const mapped = this.mapBox(box);
      this.boxEl.style.left = mapped.left + 'px';
      this.boxEl.style.top = mapped.top + 'px';
      this.boxEl.style.width = mapped.width + 'px';
      this.boxEl.style.height = mapped.height + 'px';
    },

    applyStaticLabels(lang) {
      const pack = UI[lang] || UI.en;
      this.cardEl.querySelectorAll('[data-i18n]').forEach((el) => {
        const key = el.getAttribute('data-i18n');
        if (!key) return;
        if (key === 'expand') {
          el.textContent = this.expanded ? pack.collapse : pack.expand;
          return;
        }
        if (pack[key]) el.textContent = pack[key];
      });
    },

    fillFields(source, lang) {
      const pack = UI[lang] || UI.en;
      const set = (sel, text) => {
        const el = this.cardEl.querySelector(sel);
        if (el) el.textContent = text;
      };

      set('[data-field="productName"]', source.productName || pack.unknown);
      const m = source.manufacturer || '';
      set('[data-field="manufacturer"]', (!m || m === 'Unknown') ? pack.unknownBrand : m);
      set('[data-field="specification"]', source.specification || '—');
      set('[data-field="description"]', source.description || '—');

      if (this.data) {
        const c = typeof this.data.confidence === 'number' ? this.data.confidence : 0;
        set('[data-field="confidence"]', Math.round(c * 100) + '%');
        const p = (this.data.provider || '').toLowerCase();
        set('[data-field="provider"]', p ? p.charAt(0).toUpperCase() + p.slice(1) : '—');
      }

      const label = this.boxEl.querySelector('.ar-label');
      if (label) {
        label.textContent = source.objectLabel || (this.data && this.data.objectLabel) || pack.unknown;
      }
    },

    setExpanded(open) {
      this.expanded = !!open;
      if (this.bodyEl) {
        this.bodyEl.classList.toggle('is-open', this.expanded);
        this.bodyEl.hidden = !this.expanded;
      }
      if (this.expandBtn) {
        this.expandBtn.classList.toggle('is-open', this.expanded);
        this.expandBtn.setAttribute('aria-expanded', this.expanded ? 'true' : 'false');
      }
      this.applyStaticLabels(this.lang);
    },

    toggleExpand() {
      this.setExpanded(!this.expanded);
    },

    async render(data) {
      this.data = data;
      this.source = {
        objectLabel: data.objectLabel || '',
        productName: data.productName || '',
        manufacturer: data.manufacturer || '',
        specification: data.specification || '',
        description: data.description || '',
      };
      this.cache = {};
      const saved = this.canonicalLang((window.ClientPrefs && ClientPrefs.get('resultLang')) || 'en');
      this.outputLang = saved;
      this.lang = saved;
      this.cache[saved] = Object.assign({}, this.source);

      this.positionBoxOnly();
      this.boxEl.classList.add('visible');

      this.pinTopLeft();
      this.cardEl.hidden = false;
      this.cardEl.classList.add('is-visible');
      this.paintLanguageButton();
      this.applyStaticLabels(saved);
      this.fillFields(this.source, saved);
      this.setExpanded(true);
      return true;
    },

    spokenFields() {
      const read = (name) => {
        const el = this.cardEl.querySelector('[data-field="' + name + '"]');
        return el ? el.textContent.trim() : '';
      };
      return {
        productName: read('productName'),
        manufacturer: read('manufacturer'),
        objectLabel: (this.cache[this.lang] && this.cache[this.lang].objectLabel) || (this.data && this.data.objectLabel) || '',
        description: read('description'),
      };
    },

    toggleCard() {
      this.toggleExpand();
    },

    canonicalLang(code) {
      const raw = String(code || 'en').replace(/_/g, '-');
      const hit = LANGUAGES.find((item) => item.code.toLowerCase() === raw.toLowerCase());
      return hit ? hit.code : 'en';
    },

    sameLang(a, b) {
      return this.canonicalLang(a).toLowerCase() === this.canonicalLang(b).toLowerCase();
    },

    usableText(value, fallback) {
      const text = String(value || '').trim();
      if (!text || /please select two distinct languages|mymemory warning|invalid language pair/i.test(text)) {
        return fallback;
      }
      return text;
    },

    paintLanguageButton() {
      if (!this.langButton) return;
      const item = LANGUAGES.find((entry) => entry.code === this.lang) || LANGUAGES.find((entry) => entry.code === 'en');
      this.langButton.textContent = item ? item.name : 'English';
    },

    openLanguageSheet() {
      if (!this.langSheet) return;
      this.langSheet.hidden = false;
      if (this.langSearch) {
        this.langSearch.value = '';
        this.langSearch.focus();
      }
      this.renderLanguageList('');
    },

    closeLanguageSheet() {
      if (this.langSheet) this.langSheet.hidden = true;
    },

    renderLanguageList(query) {
      if (!this.langList) return;
      const needle = String(query || '').trim().toLowerCase();
      const items = LANGUAGES.filter((item) => {
        return !needle || item.name.toLowerCase().indexOf(needle) !== -1 || item.code.toLowerCase().indexOf(needle) !== -1;
      });
      let html = '';
      let letter = '';
      items.forEach((item) => {
        const next = item.name.charAt(0).toUpperCase();
        if (!needle && next !== letter) {
          letter = next;
          html += '<div class="lang-letter">' + letter + '</div>';
        }
        const active = item.code === this.lang ? ' is-active' : '';
        html += '<button type="button" class="lang-item' + active + '" data-code="' + item.code + '">' + item.name + '</button>';
      });
      this.langList.innerHTML = html || '<p class="lang-empty">No language found.</p>';
      this.langList.querySelectorAll('.lang-item').forEach((btn) => {
        btn.addEventListener('click', () => {
          const code = btn.getAttribute('data-code') || 'en';
          if (window.ClientPrefs) ClientPrefs.set('resultLang', code);
          this.closeLanguageSheet();
          this.setLanguage(code).then((ok) => {
            if (ok && this.data && window.ScannerVoice && ClientPrefs.get('voiceEnabled') && ClientPrefs.get('autoSpeak')) {
              ScannerVoice.speakText(ScannerVoice.buildSummary(this.spokenFields()), this.lang);
            }
          });
        });
      });
    },

    async setLanguage(code) {
      if (!this.source) return false;
      const lang = this.canonicalLang(code);
      const previous = this.lang;
      this.lang = lang;
      this.paintLanguageButton();
      this.applyStaticLabels(lang);

      if (this.sameLang(lang, this.outputLang)) {
        this.fillFields(this.source, lang);
        return true;
      }
      if (this.cache[lang]) {
        this.fillFields(this.cache[lang], lang);
        return true;
      }

      this.translating = true;
      if (this.langButton) this.langButton.disabled = true;
      try {
        const translated = await this.fetchTranslation(this.source, lang);
        this.cache[lang] = translated;
        if (this.lang === lang) this.fillFields(translated, lang);
        return true;
      } catch (err) {
        this.lang = previous;
        if (window.ClientPrefs) ClientPrefs.set('resultLang', previous);
        this.paintLanguageButton();
        this.applyStaticLabels(previous);
        this.fillFields(this.cache[previous] || this.source, previous);
        console.warn('Translate failed', err);
        return false;
      } finally {
        this.translating = false;
        if (this.langButton) this.langButton.disabled = false;
      }
    },

    async fetchTranslation(data, lang) {
      const res = await fetch(this.apiBase() + '/api/translate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          target: lang,
          source: this.outputLang || 'en',
          languageName: (LANGUAGES.find((item) => item.code === lang) || {}).name || lang,
          fields: {
            objectLabel: data.objectLabel || '',
            productName: data.productName || '',
            manufacturer: data.manufacturer || '',
            specification: data.specification || '',
            description: data.description || '',
          },
        }),
      });

      let payload = null;
      try {
        payload = await res.json();
      } catch (_) {
        payload = null;
      }

      if (!res.ok || !payload || !payload.ok || !payload.data) {
        throw new Error((payload && payload.error) || 'Translation failed');
      }

      return {
        objectLabel: this.usableText(payload.data.objectLabel, data.objectLabel),
        productName: this.usableText(payload.data.productName, data.productName),
        manufacturer: this.usableText(payload.data.manufacturer, data.manufacturer),
        specification: this.usableText(payload.data.specification, data.specification),
        description: this.usableText(payload.data.description, data.description),
      };
    },
  };

  global.ArOverlay = ArOverlay;
})(window);
