(function () {
  'use strict';
  var B = window.BOOK;
  var pdfjs = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
  var $ = function (id) { return document.getElementById(id); };
  var stage = $('stage'), bookEl = $('book'), loading = $('loading'), loadTxt = $('loadTxt');
  var doc = null, flip = null, pages = [], rendered = {}, ratio = 1.414, total = 0;
  var dpr = Math.min(window.devicePixelRatio || 1, 2);

  function fail(msg) {
    loading.innerHTML = '<span>' + msg + '</span><a class="btn" href="' + B.download + '">ดาวน์โหลด PDF</a>';
  }
  if (!pdfjs || !window.St) { fail('เบราว์เซอร์นี้เปิดแบบพลิกหน้าไม่ได้'); return; }
  pdfjs.GlobalWorkerOptions.workerSrc = B.worker;

  var task = pdfjs.getDocument({ url: B.url, disableAutoFetch: true, disableStream: true });
  task.onProgress = function (p) {
    if (p.total) loadTxt.textContent = 'กำลังเปิดเล่ม… ' + Math.min(100, Math.round(p.loaded * 100 / p.total)) + '%';
  };
  task.promise.then(function (d) {
    doc = d; total = d.numPages;
    return d.getPage(1);
  }).then(function (p1) {
    var vp = p1.getViewport({ scale: 1 });
    ratio = vp.height / vp.width;
    build();
  }).catch(function (e) { console.error(e); fail('เปิดไฟล์ไม่ได้'); });

  // ขนาดหน้าที่ใหญ่สุดที่พอดีจอ
  function fitSize() {
    var w = stage.clientWidth - 24, h = stage.clientHeight - 24;
    var portrait = w < 760;
    var pw = portrait ? w : w / 2;
    var ph = pw * ratio;
    if (ph > h) { ph = h; pw = ph / ratio; }
    return { w: Math.max(200, Math.floor(pw)), h: Math.max(280, Math.floor(ph)), portrait: portrait };
  }

  function build() {
    $('pageCount').textContent = total;
    $('pageNo').max = total;
    var frag = document.createDocumentFragment();
    for (var i = 0; i < total; i++) {
      var d = document.createElement('div');
      d.className = 'rd-pg';
      if (i === 0 || i === total - 1) d.setAttribute('data-density', 'hard');
      d.innerHTML = '<canvas></canvas><span class="rd-pg-no">' + (i + 1) + '</span>';
      frag.appendChild(d); pages.push(d);
    }
    bookEl.appendChild(frag);
    var s = fitSize();
    flip = new St.PageFlip(bookEl, {
      width: s.w, height: s.h, size: 'fixed',
      showCover: true, usePortrait: true, autoSize: true,
      maxShadowOpacity: 0.45, drawShadow: true, flippingTime: 650,
      mobileScrollSupport: false, showPageCorners: true
    });
    flip.loadFromHTML(pages);
    flip.on('flip', function (e) { onPage(e.data); });
    flip.on('changeOrientation', function () { onPage(flip.getCurrentPageIndex()); });
    loading.hidden = true;
    var start = parseInt((location.hash.match(/p=(\d+)/) || [])[1], 10);
    if (start > 1 && start <= total) flip.turnToPage(start - 1);
    onPage(flip.getCurrentPageIndex());
  }

  function renderPage(i) {
    if (i < 0 || i >= total || rendered[i]) return;
    rendered[i] = true;
    var cv = pages[i].querySelector('canvas');
    doc.getPage(i + 1).then(function (p) {
      var w = pages[i].clientWidth || fitSize().w;
      var vp1 = p.getViewport({ scale: 1 });
      var vp = p.getViewport({ scale: (w * dpr) / vp1.width });
      cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
      return p.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
    }).then(function () { pages[i].classList.add('ready'); })
      .catch(function () { rendered[i] = false; });
  }
  function onPage(i) {
    $('pageNo').value = i + 1;
    // หน้าคู่: แสดงช่วงหน้าที่เห็นอยู่ (หน้าปก/ปกหลังเห็นหน้าเดียว)
    var land = flip && flip.getOrientation() === 'landscape';
    var a = i, b = i;
    if (land && i > 0) { a = (i % 2 === 1) ? i : i - 1; b = Math.min(a + 1, total - 1); }
    $('range').textContent = b > a ? '(' + (a + 1) + '–' + (b + 1) + ')' : '';
    history.replaceState(null, '', '#p=' + (i + 1));
    for (var k = i - 2; k <= i + 5; k++) renderPage(k);
    // คืนหน่วยความจำของหน้าที่อยู่ไกล
    Object.keys(rendered).forEach(function (k) {
      k = +k;
      if (Math.abs(k - i) > 14) {
        var cv = pages[k].querySelector('canvas');
        cv.width = cv.height = 0; pages[k].classList.remove('ready'); delete rendered[k];
      }
    });
  }

  $('prev').onclick = function () { flip && flip.flipPrev(); };
  $('next').onclick = function () { flip && flip.flipNext(); };
  $('first').onclick = function () { flip && flip.turnToPage(0); onPage(0); };
  $('last').onclick = function () { flip && flip.turnToPage(total - 1); onPage(total - 1); };
  $('pageNo').onchange = function () {
    var n = Math.max(1, Math.min(total, parseInt(this.value, 10) || 1));
    if (flip) { flip.turnToPage(n - 1); onPage(n - 1); }
  };
  document.addEventListener('keydown', function (e) {
    if (!flip || e.target.tagName === 'INPUT' || !$('zoomBox').hidden) {
      if (e.key === 'Escape') closeZoom();
      return;
    }
    if (e.key === 'ArrowRight' || e.key === 'PageDown') flip.flipNext();
    else if (e.key === 'ArrowLeft' || e.key === 'PageUp') flip.flipPrev();
    else if (e.key === 'Home') $('first').onclick();
    else if (e.key === 'End') $('last').onclick();
  });
  $('full').onclick = function () {
    var el = document.documentElement;
    if (document.fullscreenElement) document.exitFullscreen();
    else if (el.requestFullscreen) el.requestFullscreen();
  };
  // ปรับขนาดเล่มเมื่อหมุนจอ/ย่อหน้าต่าง
  var rt = null;
  window.addEventListener('resize', function () {
    clearTimeout(rt);
    rt = setTimeout(function () {
      if (!flip) return;
      var cur = flip.getCurrentPageIndex(), s = fitSize();
      var st = flip.getSettings();
      st.width = s.w; st.height = s.h;
      flip.update();
      rendered = {};
      onPage(cur);
    }, 250);
  });

  // ---------- ขยายหน้า ----------
  var zoom = 1.5;
  function drawZoom() {
    if (!flip) return;
    var body = $('zoomBody'); body.innerHTML = '';
    var i = flip.getCurrentPageIndex(), list = [i];
    if (flip.getOrientation() === 'landscape' && i > 0 && i + 1 < total) list = (i % 2 === 1) ? [i, i + 1] : [i - 1, i];
    $('zPct').textContent = Math.round(zoom * 100) + '%';
    list.forEach(function (n) {
      var cv = document.createElement('canvas'); body.appendChild(cv);
      doc.getPage(n + 1).then(function (p) {
        var base = Math.min(body.clientWidth - 32, 900) / list.length;
        var vp1 = p.getViewport({ scale: 1 });
        var css = base * zoom;
        var vp = p.getViewport({ scale: css * dpr / vp1.width });
        cv.width = vp.width; cv.height = vp.height; cv.style.width = css + 'px';
        p.render({ canvasContext: cv.getContext('2d'), viewport: vp });
      });
    });
  }
  function closeZoom() { $('zoomBox').hidden = true; $('zoomBody').innerHTML = ''; }
  $('zoom').onclick = function () { $('zoomBox').hidden = false; drawZoom(); };
  $('zIn').onclick = function () { zoom = Math.min(4, zoom + 0.5); drawZoom(); };
  $('zOut').onclick = function () { zoom = Math.max(1, zoom - 0.5); drawZoom(); };
  $('zClose').onclick = closeZoom;
})();
