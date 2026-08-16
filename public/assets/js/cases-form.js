(function () {
  const cfg = window.KAJANG_CASE_FORM || {};
  const form = document.getElementById('caseUpsertForm');
  const caseNumberInput = document.getElementById('case_number');
  const confirmInput = document.getElementById('confirm_existing');
  const modeBanner = document.getElementById('caseModeBanner');
  const modeBadge = document.getElementById('caseModeBadge');
  const modeTitle = document.getElementById('caseModeTitle');
  const modeHint = document.getElementById('caseModeHint');
  const bannerSummary = document.getElementById('caseLookupSummary');
  const hint = document.getElementById('caseNumberHint');
  const btnSubmitLabel = document.getElementById('btnSubmitLabel');
  const btnSubmitIcon = document.getElementById('btnSubmitIcon');
  const formTitle = document.getElementById('caseFormTitle');
  const formDesc = document.getElementById('caseFormDesc');
  const formHeaderIcon = document.getElementById('caseFormHeaderIcon');
  const noteOptionalLabel = document.getElementById('noteOptionalLabel');
  const noteHint = document.getElementById('noteHint');
  const noteField = document.getElementById('note');

  if (!form || !caseNumberInput) return;

  let lastFoundCase = cfg.existingCase || null;
  let lastPrefillNumber = null;
  let lookupTimer = null;
  let lookupSeq = 0;

  document.querySelectorAll('select.searchable').forEach(function (el) {
    if (window.TomSelect) {
      new TomSelect(el, { create: false, allowEmptyOption: true, sortField: { field: 'text', direction: 'asc' } });
    }
  });

  function initCasePicker() {
    const picker = document.getElementById('case_picker');
    if (!picker || !window.TomSelect || !cfg.searchUrl || cfg.mode === 'edit') return null;

    // Clear confusing default option label so only the search input is visible while typing
    Array.from(picker.options).forEach(function (opt) {
      if (opt.value === '') opt.textContent = '';
    });

    return new TomSelect(picker, {
      valueField: 'id',
      labelField: 'text',
      searchField: ['case_number', 'taxpayer_name', 'npwp', 'text'],
      maxOptions: 20,
      preload: 'focus',
      create: false,
      allowEmptyOption: true,
      openOnFocus: true,
      closeAfterSelect: true,
      hidePlaceholder: false,
      placeholder: 'Ketik nomor, NPWP, atau nama WP...',
      loadThrottle: 250,
      plugins: ['clear_button'],
      render: {
        option: function (data, escape) {
          if (!data.id) {
            return '<div class="option">—</div>';
          }
          const sub = [
            data.npwp ? 'NPWP ' + data.npwp : '',
            data.status_name || '',
            data.due_date_id ? 'JT ' + data.due_date_id : ''
          ].filter(Boolean).join(' · ');
          return '<div class="case-picker-option">'
            + '<div class="case-picker-option__main">' + escape(data.case_number || data.id) + ' — ' + escape(data.taxpayer_name || '') + '</div>'
            + (sub ? '<div class="case-picker-option__sub">' + escape(sub) + '</div>' : '')
            + '</div>';
        },
        item: function (data, escape) {
          if (!data.id) return '<div></div>';
          return '<div>' + escape(data.case_number || data.text || data.id) + '</div>';
        },
        no_results: function (data, escape) {
          const q = (data && data.input) ? String(data.input) : '';
          return '<div class="no-results">' + (q
            ? 'Tidak ada kasus untuk “' + escape(q) + '”.'
            : 'Ketik kata kunci untuk mencari kasus.') + '</div>';
        }
      },
      load: function (query, callback) {
        const url = cfg.searchUrl + '?q=' + encodeURIComponent(query || '');
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (res) { return res.json(); })
          .then(function (data) { callback(data.items || []); })
          .catch(function () { callback(); });
      },
      onChange: function (value) {
        if (!value) return;
        applyPickedCaseNumber(String(value).toUpperCase());
      },
      onDropdownOpen: function () {
        const input = this.control_input;
        if (input) {
          input.focus();
          // Select any leftover text so user can type immediately
          input.select();
        }
      }
    });
  }

  function applyPickedCaseNumber(number) {
    caseNumberInput.value = number;
    setConfirm(false);
    lastPrefillNumber = null;
    lookupCaseNumber();
    if (hint) hint.textContent = 'Kasus dipilih dari pencarian. Memuat data...';
  }

  const casePicker = initCasePicker();

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderSummary(caseData, target) {
    if (!target) return;
    if (!caseData) {
      target.innerHTML = '';
      target.classList.add('d-none');
      return;
    }
    const rows = [
      ['Nomor', caseData.case_number],
      ['NPWP', caseData.npwp],
      ['Nama WP', caseData.taxpayer_name],
      ['Jenis', caseData.case_type_name],
      ['Status saat ini', caseData.status_name],
      ['Sumber', caseData.source_name],
      ['Dibuat', caseData.created_date_id || caseData.created_date],
      ['Jatuh Tempo', caseData.due_date_id || caseData.due_date],
      ['Petugas', caseData.officer_name]
    ];
    target.innerHTML = '<div class="existing-summary grid">' + rows.map(function (r) {
      return '<div><span>' + escapeHtml(r[0]) + '</span><strong>' + escapeHtml(String(r[1] ?? '—')) + '</strong></div>';
    }).join('') + '</div>';
    target.classList.remove('d-none');
  }

  function setConfirm(flag) {
    if (confirmInput) confirmInput.value = flag ? '1' : '0';
  }

  function setMode(mode, caseData, message) {
    const isUpdate = mode === 'update';
    const isCreate = mode === 'create';

    if (modeBanner) {
      modeBanner.classList.remove('case-mode-banner--idle', 'case-mode-banner--create', 'case-mode-banner--update');
      modeBanner.classList.add(
        isUpdate ? 'case-mode-banner--update' : (isCreate ? 'case-mode-banner--create' : 'case-mode-banner--idle')
      );
      modeBanner.dataset.mode = mode;
    }

    if (modeBadge) {
      modeBadge.textContent = isUpdate ? 'Mode Perbarui' : (isCreate ? 'Mode Kasus Baru' : 'Siap input');
    }

    if (modeTitle) {
      if (isUpdate) {
        modeTitle.textContent = message || 'Nomor kasus sudah terdaftar — data utama akan diperbarui, riwayat tetap disimpan.';
      } else if (isCreate) {
        modeTitle.textContent = message || 'Nomor kasus belum terdaftar — data akan disimpan sebagai kasus baru.';
      } else {
        modeTitle.textContent = 'Ketik Nomor Kasus. Sistem memeriksa otomatis apakah ini kasus baru atau pembaruan.';
      }
    }

    if (modeHint) {
      if (isUpdate) {
        modeHint.innerHTML = 'Ubah field yang perlu (misalnya status Diproses → Selesai), isi catatan progress, lalu klik <strong>Perbarui Kasus</strong>.';
      } else if (isCreate) {
        modeHint.innerHTML = 'Lengkapi data di bawah, lalu klik <strong>Simpan Kasus Baru</strong>.';
      } else {
        modeHint.textContent = 'Setelah nomor valid, petunjuk mode akan muncul di sini.';
      }
    }

    if (isUpdate) {
      renderSummary(caseData, bannerSummary);
    } else if (bannerSummary) {
      bannerSummary.innerHTML = '';
      bannerSummary.classList.add('d-none');
    }

    if (formTitle) formTitle.textContent = 'Simpan/Update Kasus';
    if (formDesc) {
      formDesc.textContent = isUpdate
        ? 'Ubah data yang perlu diperbarui. Perubahan status dan field lain akan tercatat di riwayat.'
        : 'Cari kasus yang sudah ada, atau ketik nomor baru. Jika nomor sudah terdaftar, form otomatis beralih ke mode update.';
    }
    if (formHeaderIcon) {
      formHeaderIcon.innerHTML = '<i class="bi ' + (isUpdate ? 'bi-pencil-square' : 'bi-journal-plus') + '"></i>';
    }
    if (btnSubmitLabel) {
      btnSubmitLabel.textContent = isUpdate ? 'Perbarui Kasus' : 'Simpan Kasus Baru';
    }
    if (btnSubmitIcon) {
      btnSubmitIcon.className = 'bi ' + (isUpdate ? 'bi-arrow-repeat' : 'bi-check2-circle');
    }
    if (noteOptionalLabel) {
      noteOptionalLabel.textContent = isUpdate ? '(disarankan saat update)' : '(opsional)';
    }
    if (noteHint) {
      noteHint.textContent = isUpdate
        ? 'Catatan ini masuk ke riwayat perubahan. Kosongkan jika tidak ada keterangan tambahan — catatan terakhir kasus tidak akan terhapus.'
        : 'Opsional untuk kasus baru. Akan tersimpan sebagai catatan awal.';
    }
  }

  function setFieldValue(id, val) {
    const el = document.getElementById(id);
    if (!el || el.disabled || el.readOnly) return;
    const value = val == null ? '' : String(val);
    if (el.tomselect) {
      el.tomselect.setValue(value, true);
    } else {
      el.value = value;
    }
  }

  function prefillFromCase(caseData) {
    if (!caseData) return;
    setFieldValue('npwp', caseData.npwp);
    setFieldValue('taxpayer_name', caseData.taxpayer_name);
    setFieldValue('case_type_id', caseData.case_type_id);
    setFieldValue('status_id', caseData.status_id);
    setFieldValue('source_id', caseData.source_id);
    setFieldValue('created_date', caseData.created_date ? String(caseData.created_date).slice(0, 10) : '');
    setFieldValue('due_date', caseData.due_date ? String(caseData.due_date).slice(0, 10) : '');
    setFieldValue('officer_id', caseData.officer_id);
    if (noteField) noteField.value = '';
    lastPrefillNumber = String(caseData.case_number || '').toUpperCase();
  }

  async function lookupCaseNumber() {
    if (cfg.mode === 'edit') return null;

    const value = String(caseNumberInput.value || '').trim().toUpperCase();
    caseNumberInput.value = value;

    if (!/^[A-Z][0-9]{10}$/.test(value)) {
      setConfirm(false);
      lastFoundCase = null;
      setMode('idle');
      if (hint) hint.textContent = value ? 'Format belum lengkap (1 huruf + 10 angka).' : '';
      return null;
    }

    const seq = ++lookupSeq;
    if (hint) hint.textContent = 'Memeriksa nomor kasus...';

    try {
      const url = cfg.lookupUrl + '?case_number=' + encodeURIComponent(value);
      const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (seq !== lookupSeq) return null;

      if (!data.valid) {
        setConfirm(false);
        lastFoundCase = null;
        setMode('idle');
        if (hint) hint.textContent = data.message || 'Format tidak valid.';
        return data;
      }

      if (data.forbidden) {
        setConfirm(false);
        lastFoundCase = null;
        setMode('idle');
        if (hint) hint.textContent = data.message;
        return data;
      }

      if (data.found) {
        lastFoundCase = data.case;
        setConfirm(true);
        setMode('update', data.case, data.message);
        if (lastPrefillNumber !== value) {
          prefillFromCase(data.case);
        }
        if (hint) hint.textContent = 'Nomor ditemukan. Form diisi data terkini — ubah yang perlu lalu perbarui.';
        if (casePicker && casePicker.getValue() !== value) {
          try {
            casePicker.addOption({
              id: value,
              case_number: value,
              text: value + ' — ' + (data.case.taxpayer_name || ''),
              taxpayer_name: data.case.taxpayer_name || '',
              npwp: data.case.npwp || '',
              status_name: data.case.status_name || '',
              due_date_id: data.case.due_date_id || ''
            });
            casePicker.setValue(value, true);
          } catch (e) { /* ignore sync errors */ }
        }
      } else {
        lastFoundCase = null;
        lastPrefillNumber = null;
        setConfirm(false);
        setMode('create', null, data.message);
        if (hint) hint.textContent = 'Nomor belum terdaftar. Siap disimpan sebagai kasus baru.';
      }
      return data;
    } catch (err) {
      if (seq !== lookupSeq) return null;
      if (hint) hint.textContent = 'Gagal memeriksa nomor kasus. Coba lagi.';
      return null;
    }
  }

  caseNumberInput.addEventListener('input', function () {
    caseNumberInput.value = caseNumberInput.value.toUpperCase();
    setConfirm(false);
    clearTimeout(lookupTimer);
    lookupTimer = setTimeout(function () { lookupCaseNumber(); }, 350);
  });

  caseNumberInput.addEventListener('blur', function () {
    clearTimeout(lookupTimer);
    lookupCaseNumber();
  });

  form.addEventListener('submit', function () {
    if (cfg.mode === 'edit') {
      setConfirm(true);
      return;
    }
    // Existing case already confirmed via lookup mode switch
    if (lastFoundCase && confirmInput && confirmInput.value !== '1') {
      setConfirm(true);
    }
  });

  // Edit page or server redirect after needs-confirm
  if (cfg.mode === 'edit') {
    setConfirm(true);
    setMode('update', cfg.existingCase);
    lastPrefillNumber = String((cfg.existingCase && cfg.existingCase.case_number) || caseNumberInput.value || '').toUpperCase();
  } else if (cfg.needsConfirm && cfg.existingCase) {
    setConfirm(true);
    setMode('update', cfg.existingCase);
    // Keep user's submitted values (old input); only prefill if form empty
    if (!cfg.hasOldInput) {
      prefillFromCase(cfg.existingCase);
    } else {
      lastPrefillNumber = String(cfg.existingCase.case_number || '').toUpperCase();
    }
  } else if (caseNumberInput.value) {
    lookupCaseNumber();
  } else {
    setMode('idle');
  }
})();
