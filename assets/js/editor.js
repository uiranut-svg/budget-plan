(function () {
  'use strict';
  var PAL = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#475569'];
  var box = document.getElementById('cats');
  var dirty = false;
  var num = function (s) { s = String(s == null ? '' : s).replace(/[^0-9.\-]/g, ''); var v = parseFloat(s); return isNaN(v) ? 0 : Math.round(v * 100) / 100; };
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  function el(tag, attrs, html) {
    var e = document.createElement(tag);
    for (var k in attrs || {}) e.setAttribute(k, attrs[k]);
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function itemRow(it) {
    var tr = el('tr', { 'class': 'it' });
    tr.innerHTML =
      '<td><input class="i-name" placeholder="ชื่อรายการ / โครงการ" value="' + esc(it.name) + '"></td>' +
      '<td><input class="i-dep" list="deps" placeholder="หน่วยงาน" value="' + esc(it.department) + '"></td>' +
      '<td><input class="i-amt num" inputmode="decimal" placeholder="0.00" value="' + (it.amount ? money(it.amount) : '') + '"></td>' +
      '<td><input class="i-note" placeholder="หมายเหตุ" value="' + esc(it.note) + '"></td>' +
      '<td><button type="button" class="icon-btn i-del" title="ลบรายการ" aria-label="ลบรายการ">✕</button></td>';
    return tr;
  }
  function catBlock(c, i) {
    var d = el('section', { 'class': 'ed-cat' });
    d.innerHTML =
      '<div class="ed-cat-head">' +
      '<input type="color" class="c-color" value="' + esc(c.color || PAL[i % PAL.length]) + '" title="สีในกราฟ">' +
      '<input class="c-name" placeholder="ชื่อหมวด เช่น งบเงินอุดหนุน" value="' + esc(c.name) + '">' +
      '<label class="c-amt-wrap">ยอดหมวด <input class="c-amt num" inputmode="decimal" placeholder="0.00" value="' + (c.amount ? money(c.amount) : '') + '"></label>' +
      '<span class="c-sum"></span>' +
      '<span class="ed-cat-tools">' +
      '<button type="button" class="icon-btn c-up" title="เลื่อนขึ้น" aria-label="เลื่อนขึ้น">↑</button>' +
      '<button type="button" class="icon-btn c-down" title="เลื่อนลง" aria-label="เลื่อนลง">↓</button>' +
      '<button type="button" class="icon-btn danger c-del" title="ลบหมวด" aria-label="ลบหมวด">✕</button></span>' +
      '</div>' +
      '<div class="scroll-x"><table class="ed-items"><thead><tr><th>รายการ / โครงการ</th><th>หน่วยงาน</th><th class="num">จำนวนเงิน (บาท)</th><th>หมายเหตุ</th><th></th></tr></thead><tbody></tbody></table></div>' +
      '<div class="ed-cat-foot"><button type="button" class="btn btn-sm btn-ghost c-add">+ เพิ่มรายการ</button>' +
      '<button type="button" class="btn btn-sm btn-ghost c-paste">วางจาก Excel</button></div>';
    var tb = d.querySelector('tbody');
    (c.items || []).forEach(function (it) { tb.appendChild(itemRow(it)); });
    return d;
  }
  function recalc() {
    var grand = 0;
    box.querySelectorAll('.ed-cat').forEach(function (d) {
      var rows = d.querySelectorAll('tr.it'), s = 0, has = false;
      rows.forEach(function (r) { if (r.querySelector('.i-name').value.trim() !== '') has = true; s += num(r.querySelector('.i-amt').value); });
      var amt = d.querySelector('.c-amt');
      d.querySelector('.c-amt-wrap').hidden = has;
      d.querySelector('.ed-items').parentNode.hidden = rows.length === 0;
      var tot = has ? s : num(amt.value);
      d.querySelector('.c-sum').textContent = has ? 'รวม ' + money(s) + ' บาท (' + rows.length + ' รายการ)' : '';
      grand += tot;
    });
    document.getElementById('grand').textContent = money(grand);
  }
  function collect() {
    var out = [];
    box.querySelectorAll('.ed-cat').forEach(function (d) {
      var items = [];
      d.querySelectorAll('tr.it').forEach(function (r) {
        items.push({ name: r.querySelector('.i-name').value, department: r.querySelector('.i-dep').value, amount: num(r.querySelector('.i-amt').value), note: r.querySelector('.i-note').value });
      });
      out.push({ name: d.querySelector('.c-name').value, color: d.querySelector('.c-color').value, amount: num(d.querySelector('.c-amt').value), items: items });
    });
    return out;
  }

  (window.ED.cats.length ? window.ED.cats : [{ name: '', items: [] }]).forEach(function (c, i) { box.appendChild(catBlock(c, i)); });
  recalc();

  document.getElementById('addCat').onclick = function () {
    var n = box.querySelectorAll('.ed-cat').length;
    var d = catBlock({ name: '', items: [] }, n); box.appendChild(d); d.querySelector('.c-name').focus(); dirty = true; recalc();
  };
  box.addEventListener('input', function () { dirty = true; recalc(); });
  box.addEventListener('focusout', function (e) {
    if (e.target.classList.contains('i-amt') || e.target.classList.contains('c-amt')) {
      if (e.target.value.trim() !== '') e.target.value = money(num(e.target.value));
    }
  });
  box.addEventListener('click', function (e) {
    var t = e.target, d = t.closest('.ed-cat');
    if (!d) return;
    if (t.classList.contains('i-del')) { t.closest('tr').remove(); }
    else if (t.classList.contains('c-add')) { var r = itemRow({}); d.querySelector('tbody').appendChild(r); r.querySelector('input').focus(); }
    else if (t.classList.contains('c-del')) {
      var n = d.querySelectorAll('tr.it').length;
      if (!confirm('ลบหมวด "' + (d.querySelector('.c-name').value || 'ไม่มีชื่อ') + '"' + (n ? ' และรายการ ' + n + ' รายการ' : '') + '?')) return;
      d.remove();
    }
    else if (t.classList.contains('c-up') && d.previousElementSibling) box.insertBefore(d, d.previousElementSibling);
    else if (t.classList.contains('c-down') && d.nextElementSibling) box.insertBefore(d.nextElementSibling, d);
    else if (t.classList.contains('c-paste')) { openPaste(d); return; }
    else return;
    dirty = true; recalc();
  });
  // Enter ในช่องจำนวนเงินของแถวสุดท้าย = เพิ่มแถวใหม่
  box.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || !e.target.closest('tr.it')) return;
    e.preventDefault();
    var tr = e.target.closest('tr.it');
    if (!tr.nextElementSibling) { var r = itemRow({}); tr.parentNode.appendChild(r); r.querySelector('input').focus(); recalc(); }
    else tr.nextElementSibling.querySelector('input').focus();
  });

  // ---------- วางจาก Excel ----------
  var dlg = null, target = null;
  function openPaste(d) {
    target = d;
    if (!dlg) {
      dlg = el('dialog', { 'class': 'dlg' });
      dlg.innerHTML = '<form method="dialog"><h3>วางรายการจาก Excel</h3>' +
        '<p class="muted small">คัดลอกจาก Excel/Google Sheets เป็นคอลัมน์ตามลำดับ: <b>รายการ</b> | หน่วยงาน | <b>จำนวนเงิน</b> | หมายเหตุ (ถ้ามี 2 คอลัมน์ = รายการ | จำนวนเงิน)</p>' +
        '<textarea rows="10" class="p-text" placeholder="วางที่นี่ (Ctrl+V)"></textarea>' +
        '<label class="chk"><input type="checkbox" class="p-replace"> แทนที่รายการเดิมของหมวดนี้</label>' +
        '<p class="dlg-foot"><button class="btn btn-ghost" value="cancel">ยกเลิก</button> <button class="btn" value="ok">เพิ่มรายการ</button></p></form>';
      document.body.appendChild(dlg);
      dlg.addEventListener('close', function () {
        if (dlg.returnValue !== 'ok') return;
        var rows = parse(dlg.querySelector('.p-text').value);
        var tb = target.querySelector('tbody');
        if (dlg.querySelector('.p-replace').checked) tb.innerHTML = '';
        rows.forEach(function (it) { tb.appendChild(itemRow(it)); });
        dirty = true; recalc();
      });
    }
    dlg.querySelector('.p-text').value = '';
    dlg.querySelector('.p-replace').checked = false;
    dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
  }
  function parse(text) {
    var out = [];
    text.split(/\r?\n/).forEach(function (line) {
      if (!line.trim()) return;
      var c = line.split('\t').map(function (s) { return s.trim(); });
      var it;
      if (c.length === 1) it = { name: c[0], department: '', amount: 0, note: '' };
      else if (c.length === 2) it = { name: c[0], department: '', amount: num(c[1]), note: '' };
      else it = { name: c[0], department: c[1], amount: num(c[2]), note: c[3] || '' };
      if (it.name && !/^(รายการ|ชื่อรายการ|โครงการ)$/.test(it.name)) out.push(it);
    });
    return out;
  }

  document.getElementById('yearForm').addEventListener('submit', function () {
    document.getElementById('payload').value = JSON.stringify(collect());
    dirty = false;
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  document.querySelectorAll('#yearForm input[name], #yearForm textarea').forEach(function (i) { i.addEventListener('input', function () { dirty = true; }); });
})();
