(function () {
  'use strict';
  var BP = window.BP || {};
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var css = getComputedStyle(document.documentElement);
  var ink = (css.getPropertyValue('--ink-2') || '#555').trim();
  var grid = (css.getPropertyValue('--line') || '#ddd').trim();

  // ---------- กราฟ ----------
  if (window.Chart) {
    Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
    Chart.defaults.font.size = 14;
    Chart.defaults.color = ink;
    var s = BP.share || { labels: [], values: [], colors: [] };
    var total = s.values.reduce(function (a, b) { return a + b; }, 0);
    var el = document.getElementById('chShare');
    if (el && s.values.length) {
      new Chart(el, {
        type: 'doughnut',
        data: { labels: s.labels, datasets: [{ data: s.values, backgroundColor: s.colors, borderWidth: 2, borderColor: (css.getPropertyValue('--surface') || '#fff').trim() }] },
        options: {
          maintainAspectRatio: false, cutout: '58%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
            tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + money(c.raw) + ' บาท (' + (total ? (c.raw * 100 / total).toFixed(2) : 0) + '%)'; } } }
          }
        }
      });
    }
    el = document.getElementById('chCat');
    if (el && s.values.length) {
      new Chart(el, {
        type: 'bar',
        data: { labels: s.labels, datasets: [{ data: s.values, backgroundColor: s.colors, borderRadius: 6 }] },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + money(c.raw) + ' บาท'; } } } },
          scales: {
            x: { grid: { color: grid }, ticks: { callback: function (v) { return v >= 1e6 ? (v / 1e6) + ' ล.' : Number(v).toLocaleString('th-TH'); } } },
            y: { grid: { display: false } }
          }
        }
      });
    }
    var tickM = function (v) { return v >= 1e6 ? (v / 1e6) + ' ล.' : Number(v).toLocaleString('th-TH'); };
    var surface = (css.getPropertyValue('--surface') || '#fff').trim();

    // แหล่งเงิน: สัดส่วน (ประมาณการ + ยกมา) และประมาณการเทียบรับจริง
    var f = BP.fund;
    el = document.getElementById('chFund');
    if (el && f && f.labels.length) {
      var ftot = f.avail.reduce(function (a, b) { return a + b; }, 0);
      new Chart(el, {
        type: 'doughnut',
        data: { labels: f.labels, datasets: [{ data: f.avail, backgroundColor: f.colors, borderWidth: 2, borderColor: surface }] },
        options: {
          maintainAspectRatio: false, cutout: '58%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
            tooltip: { callbacks: { label: function (t) { return ' ' + t.label + ': ' + money(t.raw) + ' บาท (' + (ftot ? (t.raw * 100 / ftot).toFixed(2) : 0) + '%)'; } } }
          }
        }
      });
    }
    el = document.getElementById('chFundRecv');
    if (el && f && f.labels.length) {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: f.labels,
          datasets: [
            { label: 'ประมาณการ', data: f.estimate, backgroundColor: 'rgba(124,13,18,.28)', borderColor: '#7c0d12', borderWidth: 1, borderRadius: 4 },
            { label: 'รับจริง', data: f.received, backgroundColor: f.colors, borderRadius: 4 }
          ]
        },
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (t) { return ' ' + t.dataset.label + ': ' + money(t.raw) + ' บาท'; } } } },
          scales: { y: { grid: { color: grid }, ticks: { callback: tickM } }, x: { grid: { display: false } } }
        }
      });
    }

    // โครงการ: ตามฝ่าย (อนุมัติ/เบิกจ่าย) และรายไตรมาส
    var pj = BP.pj;
    el = document.getElementById('chDiv');
    if (el && pj && pj.div.labels.length) {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: pj.div.labels,
          datasets: [
            { label: 'งบอนุมัติ', data: pj.div.approved, backgroundColor: '#b3141b', borderRadius: 4 },
            { label: 'เบิกจ่ายแล้ว', data: pj.div.spent, backgroundColor: '#c9a227', borderRadius: 4 }
          ]
        },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (t) { return ' ' + t.dataset.label + ': ' + money(t.raw) + ' บาท'; } } } },
          scales: { x: { grid: { color: grid }, ticks: { callback: tickM } }, y: { grid: { display: false } } }
        }
      });
    }
    el = document.getElementById('chQ');
    if (el && pj) {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: ['ไตรมาส 1 (ต.ค.–ธ.ค.)', 'ไตรมาส 2 (ม.ค.–มี.ค.)', 'ไตรมาส 3 (เม.ย.–มิ.ย.)', 'ไตรมาส 4 (ก.ค.–ก.ย.)'],
          datasets: [{ label: 'งบตามแผน', data: pj.q, backgroundColor: ['#7c0d12', '#b3141b', '#d9480f', '#c9a227'], borderRadius: 6 }]
        },
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (t) { return ' ' + money(t.raw) + ' บาท'; } } } },
          scales: { y: { grid: { color: grid }, ticks: { callback: tickM } }, x: { grid: { display: false } } }
        }
      });
    }

    var c = BP.cmp;
    el = document.getElementById('chCmp');
    if (el && c && c.years.length > 1) {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: c.series.map(function (x) { return x.name; }),
          datasets: c.years.map(function (y, i) {
            var a = 0.35 + 0.65 * (i + 1) / c.years.length;
            return { label: 'ปี ' + y, data: c.series.map(function (x) { return x.values[i]; }), backgroundColor: 'rgba(179,20,27,' + a.toFixed(2) + ')', borderRadius: 4 };
          })
        },
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (t) { return ' ' + t.dataset.label + ': ' + money(t.raw) + ' บาท'; } } } },
          scales: {
            y: { grid: { color: grid }, ticks: { callback: function (v) { return v >= 1e6 ? (v / 1e6) + ' ล.' : Number(v).toLocaleString('th-TH'); } } },
            x: { grid: { display: false } }
          }
        }
      });
    }
  }

  // ---------- เปิด/ปิดรายการย่อย ----------
  function toggle(row, force) {
    var open = force !== undefined ? force : row.getAttribute('aria-expanded') !== 'true';
    row.setAttribute('aria-expanded', open ? 'true' : 'false');
    var n = row.nextElementSibling;
    while (n && n.classList.contains('item-row')) { n.hidden = !open; n = n.nextElementSibling; }
  }
  document.querySelectorAll('.cat-row.has-items').forEach(function (r) {
    r.addEventListener('click', function () { toggle(r); });
    r.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(r); } });
  });

  // ---------- พับ/กางรายการย่อยทั้งตาราง (เริ่มต้น = กางทั้งหมด) ----------
  var foldState = {};
  document.querySelectorAll('.js-fold').forEach(function (b) {
    var id = b.getAttribute('data-target');
    b.addEventListener('click', function () {
      foldState[id] = !foldState[id];
      document.querySelectorAll('#' + id + ' .cat-row.has-items').forEach(function (r) { toggle(r, !foldState[id]); });
      b.textContent = foldState[id] ? 'กางรายการย่อยทั้งหมด' : 'พับรายการย่อย';
    });
  });

  // ---------- แท็บชุดความสอดคล้อง ----------
  document.querySelectorAll('.seg-btn[data-set]').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-set');
      document.querySelectorAll('.seg-btn[data-set]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      document.querySelectorAll('.align-set').forEach(function (p) { p.hidden = p.getAttribute('data-set') !== k; });
    });
  });

  // ---------- ค้นหา ----------
  var q = document.getElementById('q');
  if (q) q.addEventListener('input', function () {
    var t = q.value.trim().toLowerCase();
    document.querySelectorAll('#tblDetail tbody.cat').forEach(function (tb) {
      var cat = tb.querySelector('.cat-row');
      var items = tb.querySelectorAll('.item-row');
      if (!t) { tb.hidden = false; if (items.length) toggle(cat, !foldState.tblDetail); items.forEach(function (i) { i.classList.remove('hit'); }); return; }
      var catHit = cat.textContent.toLowerCase().indexOf(t) >= 0, any = false;
      items.forEach(function (i) {
        var hit = i.textContent.toLowerCase().indexOf(t) >= 0;
        i.hidden = !(hit || catHit); i.classList.toggle('hit', hit); if (hit) any = true;
      });
      cat.setAttribute('aria-expanded', (any || catHit) && items.length ? 'true' : 'false');
      tb.hidden = !(any || catHit);
    });
  });

  // ---------- ปกเล่มแผน (วาดหน้าแรกของ PDF เมื่อเลื่อนมาถึง) ----------
  var covers = document.querySelectorAll('canvas[data-pdf]');
  if (!covers.length) return;
  var lib = null, waiting = [];
  function loadLib(cb) {
    if (lib) return cb(lib);
    waiting.push(cb);
    if (waiting.length > 1) return;
    var sc = document.createElement('script');
    sc.src = BP.pdfjs;
    sc.onload = function () {
      lib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
      lib.GlobalWorkerOptions.workerSrc = BP.worker;
      waiting.forEach(function (f) { f(lib); }); waiting = [];
    };
    document.head.appendChild(sc);
  }
  function draw(cv) {
    loadLib(function (pdfjs) {
      pdfjs.getDocument({ url: cv.getAttribute('data-pdf'), disableAutoFetch: true, disableStream: true }).promise.then(function (doc) {
        return doc.getPage(1).then(function (p) {
          var vp1 = p.getViewport({ scale: 1 });
          var scale = (cv.parentNode.clientWidth * (window.devicePixelRatio || 1)) / vp1.width;
          var vp = p.getViewport({ scale: scale });
          cv.width = vp.width; cv.height = vp.height;
          return p.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () {
            cv.parentNode.classList.add('ready'); doc.destroy();
          });
        });
      }).catch(function () { });
    });
  }
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); draw(e.target); } });
    }, { rootMargin: '200px' });
    covers.forEach(function (cv) { io.observe(cv); });
  } else covers.forEach(draw);
})();
