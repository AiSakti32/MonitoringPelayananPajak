(function () {
  const data = window.KAJANG_DASHBOARD || {};

  document.querySelectorAll('select.searchable').forEach(function (el) {
    if (!window.TomSelect || el.tomselect) return;
    new TomSelect(el, {
      create: false,
      allowEmptyOption: true,
      maxOptions: 500,
      placeholder: el.getAttribute('data-placeholder') || 'Pilih...',
      sortField: { field: 'text', direction: 'asc' }
    });
  });

  if (!window.Chart) return;

  const fontFamily = '"Plus Jakarta Sans", system-ui, sans-serif';
  Chart.defaults.font.family = fontFamily;
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#64748b';
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(11, 31, 58, 0.92)';
  Chart.defaults.plugins.tooltip.cornerRadius = 10;
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.titleFont = { family: fontFamily, weight: '600' };
  Chart.defaults.plugins.tooltip.bodyFont = { family: fontFamily };

  const barColor = '#6BA3D4';
  const pastelPalette = [
    '#6BA3D4',
    '#8B7BC8',
    '#6FBF8A',
    '#E0A06A',
    '#D988A0',
    '#5FB8B2',
    '#C4A574',
    '#7A9BC4'
  ];

  const centerTotalPlugin = {
    id: 'centerTotal',
    afterDraw: function (chart) {
      if (chart.config.type !== 'doughnut') return;
      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data || !meta.data.length) return;
      const values = chart.data.datasets[0].data || [];
      let total = 0;
      for (let i = 0; i < values.length; i++) total += Number(values[i]) || 0;

      const { ctx } = chart;
      const x = meta.data[0].x;
      const y = meta.data[0].y;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#0B1F3A';
      ctx.font = '700 28px ' + fontFamily;
      ctx.fillText(String(total), x, y - 8);
      ctx.fillStyle = '#64748b';
      ctx.font = '600 12px ' + fontFamily;
      ctx.fillText('Total', x, y + 16);
      ctx.restore();
    }
  };

  function qs(params) {
    const base = Object.assign({}, data.links.filters || {}, params || {});
    Object.keys(base).forEach(function (k) {
      if (base[k] === null || base[k] === '' || base[k] === undefined) delete base[k];
    });
    const s = new URLSearchParams(base).toString();
    return s ? ('?' + s) : '';
  }

  function goCases(extra) {
    window.location.href = data.links.casesBase + qs(extra);
  }

  function goOfficer(officerId) {
    window.location.href = data.links.casesBase + qs({ officer_id: officerId });
  }

  function fitHorizontalCanvas(el, rowCount, pxPerRow) {
    const n = Math.max(0, rowCount | 0);
    const rowH = pxPerRow || 36;
    const h = n === 0 ? 160 : Math.max(130, Math.min(560, 56 + n * rowH));
    const wrap = el.closest('.chart-wrap');
    if (wrap) {
      wrap.style.height = h + 'px';
      wrap.style.minHeight = h + 'px';
    }
    el.style.height = h + 'px';
    el.height = h;
  }

  /** Keep start of text; ellipsis only on the right. */
  function ellipsizeEnd(text, maxChars) {
    const s = String(text || '');
    if (maxChars < 2 || s.length <= maxChars) return s;
    return s.slice(0, maxChars - 1).trimEnd() + '…';
  }

  /**
   * Wrap label to at most 2 lines (word-aware). Ellipsis only at end of line 2.
   * @returns {string|string[]}
   */
  function wrapLabelTwoLines(label, maxCharsPerLine) {
    const text = String(label || '').trim().replace(/\s+/g, ' ');
    if (!text) return '';
    const max = Math.max(8, maxCharsPerLine | 0);
    if (text.length <= max) return text;

    const words = text.split(' ');
    let line1 = '';
    let i = 0;
    for (; i < words.length; i++) {
      const next = line1 ? (line1 + ' ' + words[i]) : words[i];
      if (line1 && next.length > max) break;
      if (!line1 && words[i].length > max) {
        line1 = ellipsizeEnd(words[i], max);
        i++;
        break;
      }
      line1 = next;
    }

    let line2 = words.slice(i).join(' ');
    if (!line2) return line1;
    if (line2.length > max) line2 = ellipsizeEnd(line2, max);
    return [line1, line2];
  }

  function estimateCharsForWidth(px) {
    // ~7.2px per character at 12px Plus Jakarta Sans
    return Math.max(12, Math.floor((px - 16) / 7.2));
  }

  function resolveYLabelWidth(chartWidth, preferred) {
    const w = Math.max(0, chartWidth | 0);
    const minBarArea = 100;
    const maxByBars = Math.max(110, w - minBarArea);
    const maxByShare = Math.floor(w * 0.55);
    const floor = Math.min(110, preferred);
    return Math.max(floor, Math.min(preferred, maxByBars, maxByShare || preferred));
  }

  /** Draw category values at the end of horizontal bars (no extra dependency). */
  const barEndValuePlugin = {
    id: 'barEndValue',
    afterDatasetsDraw: function (chart) {
      const opts = chart.options.plugins && chart.options.plugins.barEndValue;
      if (!opts || opts.display === false) return;
      if (chart.options.indexAxis !== 'y') return;

      const { ctx } = chart;
      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data) return;
      const values = chart.data.datasets[0].data || [];

      ctx.save();
      ctx.fillStyle = opts.color || '#475569';
      ctx.font = (opts.fontWeight || '600') + ' ' + (opts.fontSize || 11) + 'px ' + fontFamily;
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';

      meta.data.forEach(function (bar, i) {
        if (!bar || bar.hidden) return;
        const value = values[i];
        if (value === null || value === undefined) return;
        const x = bar.x + (opts.offset || 8);
        const y = bar.y;
        const text = String(value);
        const maxX = chart.chartArea.right + 4;
        if (x > chart.chartArea.right + 36) return;
        ctx.fillText(text, Math.min(x, maxX), y);
      });
      ctx.restore();
    }
  };

  function doughnut(id, rows, onClick) {
    const el = document.getElementById(id);
    if (!el || !rows || !rows.length) return null;
    return new Chart(el, {
      type: 'doughnut',
      data: {
        labels: rows.map(function (r) { return r.label; }),
        datasets: [{
          data: rows.map(function (r) { return r.value; }),
          backgroundColor: pastelPalette.slice(0, rows.length),
          borderWidth: 4,
          borderColor: '#fff',
          hoverOffset: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        layout: {
          padding: { top: 18, bottom: 8, left: 8, right: 8 }
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              pointStyle: 'circle',
              padding: 18,
              boxWidth: 9,
              font: { family: fontFamily, size: 12, weight: '500' },
              generateLabels: function (chart) {
                const ds = chart.data.datasets[0];
                return (chart.data.labels || []).map(function (label, i) {
                  const value = ds.data[i];
                  return {
                    text: label + '  ·  ' + value,
                    fillStyle: ds.backgroundColor[i],
                    strokeStyle: '#fff',
                    lineWidth: 0,
                    hidden: false,
                    index: i,
                    pointStyle: 'circle'
                  };
                });
              }
            }
          }
        },
        onClick: function (_evt, elements) {
          if (!elements.length) return;
          onClick(rows[elements[0].index]);
        }
      },
      plugins: [centerTotalPlugin]
    });
  }

  /**
   * @param {string} id
   * @param {Array} rows
   * @param {Function} onClick
   * @param {boolean|object} horizontalOrOpts
   */
  function bar(id, rows, onClick, horizontalOrOpts) {
    const el = document.getElementById(id);
    if (!el || !rows || !rows.length) return null;

    const opts = typeof horizontalOrOpts === 'object' && horizontalOrOpts !== null
      ? horizontalOrOpts
      : { horizontal: !!horizontalOrOpts };

    const horizontal = !!opts.horizontal;
    const preferredYWidth = opts.yLabelWidth || 180;
    const wrapLabels = opts.wrapLabels === true;
    const showValues = opts.showValues === true;
    const pxPerRow = opts.pxPerRow || (wrapLabels ? 46 : 36);

    if (horizontal) {
      fitHorizontalCanvas(el, rows.length, pxPerRow);
    }

    const fullLabels = rows.map(function (r) { return String(r.label || ''); });

    const plugins = [];
    if (showValues) plugins.push(barEndValuePlugin);

    return new Chart(el, {
      type: 'bar',
      data: {
        labels: fullLabels,
        datasets: [{
          label: 'Jumlah',
          data: rows.map(function (r) { return r.value; }),
          backgroundColor: barColor,
          borderRadius: 10,
          borderSkipped: false,
          maxBarThickness: horizontal ? 22 : 26
        }]
      },
      options: {
        indexAxis: horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        layout: {
          padding: horizontal
            ? { top: 4, bottom: 4, left: 4, right: showValues ? 28 : 8 }
            : { top: 0, bottom: 0, left: 0, right: 0 }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: function (items) {
                const idx = items[0] && items[0].dataIndex;
                return rows[idx] ? String(rows[idx].label || '') : '';
              },
              label: function (item) {
                const idx = item.dataIndex;
                const value = rows[idx] ? rows[idx].value : item.raw;
                return 'Jumlah: ' + value;
              }
            }
          },
          barEndValue: showValues ? { display: true, offset: 8, fontSize: 11, color: '#475569' } : undefined
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.18)', drawBorder: false },
            ticks: {
              precision: 0,
              font: { family: fontFamily, size: 11 },
              display: !showValues
            }
          },
          y: {
            grid: { display: !horizontal, color: 'rgba(148, 163, 184, 0.12)', drawBorder: false },
            afterFit: horizontal
              ? function (scale) {
                  const chartW = scale.chart && scale.chart.width ? scale.chart.width : preferredYWidth * 2;
                  scale.width = resolveYLabelWidth(chartW, preferredYWidth);
                }
              : undefined,
            ticks: {
              autoSkip: false,
              padding: horizontal ? 12 : 4,
              color: '#64748b',
              font: { family: fontFamily, size: 12, weight: '500', lineHeight: 1.25 },
              callback: function (value) {
                const label = this.getLabelForValue
                  ? this.getLabelForValue(value)
                  : fullLabels[value];
                if (!horizontal) return label;
                const axisW = (this.width && this.width > 48) ? this.width : preferredYWidth;
                const maxChars = estimateCharsForWidth(axisW);
                if (!wrapLabels) {
                  return ellipsizeEnd(label, maxChars);
                }
                return wrapLabelTwoLines(label, maxChars);
              }
            }
          }
        },
        onClick: function (_evt, elements) {
          if (!elements.length) return;
          onClick(rows[elements[0].index]);
        }
      },
      plugins: plugins
    });
  }

  doughnut('chartStatus', data.status || [], function (row) {
    if (row.status_id) goCases({ status_id: row.status_id });
  });

  bar('chartTypes', data.types || [], function (row) {
    if (row.case_type_id) goCases({ case_type_id: row.case_type_id });
  }, {
    horizontal: true,
    yLabelWidth: 240,
    wrapLabels: true,
    showValues: true,
    pxPerRow: 46
  });

  bar('chartPriority', data.priority || [], function () {
    goCases({});
  }, {
    horizontal: true,
    yLabelWidth: 215,
    wrapLabels: true,
    showValues: true,
    pxPerRow: 44
  });

  bar('chartWorkload', data.workload || [], function (row) {
    if (row.officer_id) goOfficer(row.officer_id);
  }, {
    horizontal: true,
    yLabelWidth: 120,
    wrapLabels: false,
    showValues: false,
    pxPerRow: 36
  });
})();
