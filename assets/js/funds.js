(function () {
  'use strict';
  var PAL = ['#b3141b', '#1f3a68', '#c9a227', '#2f7d4f', '#7a4a9e', '#0e7490', '#d9480f', '#6b5b4e', '#e05a6a', '#475569'];
  var tb = document.querySelector('#fundTbl tbody');
  var dirty = false;
  var num = function (s) { s = String(s == null ? '' : s).replace(/[^0-9.\-]/g, ''); var v = parseFloat(s); return isNaN(v) ? 0 : Math.round(v * 100) / 100; };
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var amt = function (v) { return v ? money(v) : ''; };

  function row(f) {
    var tr = document.createElement('tr');
    tr.className = 'fr lv' + (f.level ? 1 : 0);
    tr.dataset.id = f.id || '';
    var top = !f.level;
    tr.innerHTML =
      '<td>' + (top ? '<input type="color" class="f-color" value="' + esc(f.color || PAL[tb.querySelectorAll('tr.lv0').length % PAL.length]) + '" title="สีในกราฟ">' : '') + '</td>' +
      '<td><input class="f-name" placeholder="' + (top ? 'แหล่งเงินหลัก เช่น เงินอุดหนุน' : 'แหล่งย่อย เช่น ค่าจัดการเรียนการสอน') + '" value="' + esc(f.name) + '"></td>' +
      '<td><input class="f-est num" inputmode="decimal" value="' + amt(f.estimate) + '"></td>' +
      '<td><input class="f-carry num" inputmode="decimal" value="' + amt(f.carry_in) + '"></td>' +
      '<td><input class="f-recv num" inputmode="decimal" value="' + amt(f.received) + '"></td>' +
      '<td><input class="f-note" value="' + esc(f.note) + '"></td>' +
      '<td class="nowrap">' + (top ? '<button type="button" class="btn btn-sm btn-ghost f-sub" title="เพิ่มแหล่งย่อย">+ ย่อย</button> ' : '') +
      '<button type="button" class="icon-btn f-up" title="เลื่อนขึ้น" aria-label="เลื่อนขึ้น">↑</button> ' +
      '<button type="button" class="icon-btn f-down" title="เลื่อนลง" aria-label="เลื่อนลง">↓</button> ' +
      '<button type="button" class="icon-btn danger f-del" title="ลบ" aria-label="ลบ">✕</button></td>';
    return tr;
  }
  // กลุ่ม = แถวหลัก + แถวย่อยที่ตามมา
  function group(tr) {
    var g = [tr], n = tr.nextElementSibling;
    if (tr.classList.contains('lv0')) while (n && n.classList.contains('lv1')) { g.push(n); n = n.nextElementSibling; }
    return g;
  }
  function recalc() {
    var est = 0, carry = 0, recv = 0;
    tb.querySelectorAll('tr.lv0').forEach(function (t) {
      var g = group(t), has = g.length > 1;
      ['f-est', 'f-carry', 'f-recv'].forEach(function (k) {
        var inp = t.querySelector('.' + k);
        inp.disabled = has;
        if (has) { var s = 0; g.slice(1).forEach(function (c) { s += num(c.querySelector('.' + k).value); }); inp.value = money(s); }
      });
      est += num(t.querySelector('.f-est').value); carry += num(t.querySelector('.f-carry').value); recv += num(t.querySelector('.f-recv').value);
    });
    document.getElementById('gEst').textContent = money(est);
    document.getElementById('gCarry').textContent = money(carry);
    document.getElementById('gRecv').textContent = money(recv);
  }
  (window.FD || []).forEach(function (f) { tb.appendChild(row(f)); });
  if (!tb.children.length) tb.appendChild(row({ level: 0 }));
  recalc();

  document.getElementById('addTop').onclick = function () { var r = row({ level: 0 }); tb.appendChild(r); r.querySelector('.f-name').focus(); dirty = true; recalc(); };
  tb.addEventListener('input', function () { dirty = true; recalc(); });
  tb.addEventListener('focusout', function (e) {
    if (e.target.classList.contains('num') && !e.target.disabled && e.target.value.trim() !== '') e.target.value = money(num(e.target.value));
  });
  tb.addEventListener('click', function (e) {
    var t = e.target, tr = t.closest('tr');
    if (!tr) return;
    var g = group(tr);
    if (t.classList.contains('f-sub')) {
      var r = row({ level: 1 }), last = g[g.length - 1];
      last.parentNode.insertBefore(r, last.nextSibling); r.querySelector('.f-name').focus();
    } else if (t.classList.contains('f-del')) {
      var nm = tr.querySelector('.f-name').value || 'ไม่มีชื่อ';
      if (!confirm('ลบ "' + nm + '"' + (g.length > 1 ? ' และแหล่งย่อย ' + (g.length - 1) + ' รายการ' : '') + '?\nโครงการที่ใช้แหล่งเงินนี้จะไม่มีแหล่งเงิน')) return;
      g.forEach(function (x) { x.remove(); });
    } else if (t.classList.contains('f-up')) {
      var p = tr.previousElementSibling;
      if (tr.classList.contains('lv0')) { while (p && p.classList.contains('lv1')) p = p.previousElementSibling; if (!p) return; g.forEach(function (x) { tb.insertBefore(x, p); }); }
      else { if (!p || p.classList.contains('lv0')) return; tb.insertBefore(tr, p); }
    } else if (t.classList.contains('f-down')) {
      var n = g[g.length - 1].nextElementSibling;
      if (!n) return;
      if (tr.classList.contains('lv0')) { var ng = group(n); var after = ng[ng.length - 1].nextSibling; g.forEach(function (x) { tb.insertBefore(x, after); }); }
      else { if (n.classList.contains('lv0')) return; tb.insertBefore(n, tr); }
    } else return;
    dirty = true; recalc();
  });
  document.getElementById('fundForm').addEventListener('submit', function () {
    var out = [];
    tb.querySelectorAll('tr.fr').forEach(function (r) {
      var c = r.querySelector('.f-color');
      out.push({ id: +r.dataset.id || 0, level: r.classList.contains('lv1') ? 1 : 0, name: r.querySelector('.f-name').value, color: c ? c.value : '',
        estimate: num(r.querySelector('.f-est').value), carry_in: num(r.querySelector('.f-carry').value), received: num(r.querySelector('.f-recv').value), note: r.querySelector('.f-note').value });
    });
    document.getElementById('payload').value = JSON.stringify(out);
    dirty = false;
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
