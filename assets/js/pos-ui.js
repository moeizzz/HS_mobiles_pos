/* =====================================================================
   POS UI enhancements (jQuery 3, Bootstrap 4.6, Flatpickr)
   Loaded in footer.php. Never renames ids/names - only adds behaviour.
   Public helpers:  window.PosUI.toast(msg, 'ok'|'warn'),  .pta(status),  .money(n),  .esc(s)
   ===================================================================== */
(function ($) {
  'use strict';

  var BASE = window.POS_BASE || '';          // set in header.php ("../../" on module pages)
  var SEARCH_URL = BASE + 'modules/sales/search.php';

  /* ---------- helpers ---------- */
  function esc(s) { return $('<span>').text(s == null ? '' : String(s)).html(); }
  function money(n) {
    n = parseFloat(n) || 0;
    return 'Rs. ' + n.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }
  function pta(status) {
    if (!status) return '';
    var s = String(status), cls = 'pta-non', ico = 'fa-exclamation-circle';
    if (/jv|locked|patch|cpid/i.test(s)) { cls = 'pta-jv'; ico = 'fa-lock'; }
    else if (/non/i.test(s))             { cls = 'pta-non'; ico = 'fa-exclamation-circle'; }
    else if (/pta|approved|official/i.test(s)) { cls = 'pta-ok'; ico = 'fa-check-circle'; }
    var label = /jv|locked/i.test(s) ? 'JV/Locked' : /patch|cpid/i.test(s) ? 'Patch' : /non/i.test(s) ? 'Non-PTA' : 'PTA Approved';
    return '<span class="pta-badge ' + cls + '"><i class="fas ' + ico + '"></i>' + label + '</span>';
  }
  function toast(msg, type) {
    var $h = $('#toastHost');
    if (!$h.length) $h = $('<div id="toastHost"></div>').appendTo('body');
    var $t = $('<div class="pos-toast ' + (type || 'ok') + '"><i class="fas ' + (type === 'warn' ? 'fa-exclamation-triangle' : 'fa-check-circle') + '"></i><span></span></div>');
    $t.find('span').text(msg);
    $h.append($t);
    setTimeout(function () { $t.fadeOut(200, function () { $t.remove(); }); }, 1800);
  }
  function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }
  window.PosUI = { toast: toast, pta: pta, money: money, esc: esc, debounce: debounce, searchUrl: SEARCH_URL };

  /* ---------- live shop date / time ---------- */
  function tickClock() {
    var $c = $('#shopClock'); if (!$c.length) return;
    var n = new Date();
    $c.find('.d').text(n.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }));
    $c.find('.t').text(n.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
  }

  /* ---------- keyboard shortcuts: F2 sale, F3 add mobile, F4 receive payment, Ctrl+K / "/" search ---------- */
  $(document).on('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase(), typing = /input|textarea|select/.test(tag) || e.target.isContentEditable;
    var map = { F2: '.qa-sale', F3: '.qa-mobile', F4: '.qa-pay' };
    if (map[e.key]) {
      var href = $(map[e.key]).attr('href');
      if (href) { e.preventDefault(); window.location.href = href; }
      return;
    }
    if ((e.ctrlKey && e.key.toLowerCase() === 'k') || (!typing && e.key === '/')) {
      var $s = $('#globalSearch');
      if ($s.length) { e.preventDefault(); $s.focus().select(); }
    }
  });

  /* ---------- global search (IMEI / customer) ---------- */
  function initGlobalSearch() {
    var $in = $('#globalSearch'), $pop = $('#globalSearchPop');
    if (!$in.length) return;
    var req = 0;
    function render(d) {
      var h = '';
      if (d.serials && d.serials.length) {
        h += '<div class="sp-head">Mobiles / IMEI</div>';
        d.serials.forEach(function (s) {
          var st = s.status === 'available' ? '<span class="status-pill status-in">In Stock</span>' : '<span class="status-pill status-sold">' + esc(s.status) + '</span>';
          h += '<a class="sp-item" href="' + BASE + 'modules/inventory/units.php?q=' + encodeURIComponent(s.imei || s.name) + '">' +
               '<div class="sp-main"><div class="sp-title">' + esc(s.name) + ' ' + pta(s.pta) + '</div>' +
               '<div class="sp-sub"><span class="imei">' + esc(s.imei || '') + '</span> ' + esc([s.storage, s.color].filter(Boolean).join(' / ')) + '</div></div>' +
               '<div class="text-right"><div class="sp-price">' + money(s.price) + '</div>' + st + '</div></a>';
        });
      }
      if (d.customers && d.customers.length) {
        h += '<div class="sp-head">Customers</div>';
        d.customers.forEach(function (c) {
          var b = parseFloat(c.balance) || 0;
          h += '<a class="sp-item" href="' + BASE + 'modules/customers/view.php?id=' + c.id + '">' +
               '<div class="sp-main"><div class="sp-title">' + esc(c.name) + '</div><div class="sp-sub">' + esc(c.phone || '') + ' <span class="cnic">' + esc(c.cnic || '') + '</span></div></div>' +
               '<span class="khata-badge ' + (b > 0 ? 'due' : 'clear') + '">' + (b > 0 ? 'Khata ' + money(b) : 'Clear') + '</span></a>';
        });
      }
      $pop.html(h || '<div class="sp-empty">No IMEI or customer found</div>').addClass('show');
    }
    var run = debounce(function () {
      var q = $.trim($in.val());
      if (q.length < 2) { $pop.removeClass('show'); return; }
      var my = ++req;
      $.getJSON(SEARCH_URL, { q: q }, function (d) { if (my === req) render(d); });
    }, 220);
    $in.on('input', run).on('focus', function () { if ($pop.children().length) $pop.addClass('show'); });
    $in.on('keydown', function (e) {
      if (e.key === 'Escape') { $pop.removeClass('show'); $in.blur(); }
      if (e.key === 'Enter') { e.preventDefault(); var $f = $pop.find('.sp-item').first(); if ($f.length) window.location.href = $f.attr('href'); }
    });
    $(document).on('click', function (e) { if (!$(e.target).closest('.global-search').length) $pop.removeClass('show'); });
  }

  /* ---------- CNIC auto-format  XXXXX-XXXXXXX-X ---------- */
  function fmtCnic(v) {
    var d = String(v).replace(/\D/g, '').slice(0, 13);
    if (d.length > 12) return d.slice(0, 5) + '-' + d.slice(5, 12) + '-' + d.slice(12);
    if (d.length > 5) return d.slice(0, 5) + '-' + d.slice(5);
    return d;
  }
  $(document).on('input', '.cnic-input, [name="seller_cnic"], [name="walkin_cnic"]', function () {
    var pos = this.value;
    var f = fmtCnic(pos);
    if (f !== pos) this.value = f;
    $(this).addClass('cnic');
  });

  /* ---------- IMEI: digits only + live 15-char counter ---------- */
  function initImei() {
    $('.imei-input').each(function () {
      var $i = $(this);
      if ($i.data('counter')) return;
      var $c = $('<span class="char-counter">0/15</span>');
      var $label = $i.closest('.form-group').find('label').first();
      if ($label.length) $label.append($c); else $i.after($c);
      $i.data('counter', $c);
      var upd = function () {
        var n = $i.val().length;
        $c.text(n + '/15').toggleClass('ok', n === 15).toggleClass('bad', n > 0 && n < 15);
      };
      $i.on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 15); upd(); });
      upd();
    });
  }

  /* ---------- profit & margin calculator (purchase vs sale price) ---------- */
  function initProfit() {
    var $b = $('#profitBadge'), $p = $('[name="purchase_price"]'), $s = $('[name="sale_price"]');
    if (!$b.length || !$p.length || !$s.length) return;
    function calc() {
      var cost = parseFloat($p.val()) || 0, sale = parseFloat($s.val()) || 0;
      if (!cost || !sale) { $b.html('<i class="fas fa-calculator"></i> Profit: -').attr('class', 'profit-badge'); return; }
      var pr = sale - cost, mg = sale ? (pr / sale * 100) : 0;
      $b.attr('class', 'profit-badge ' + (pr >= 0 ? 'gain' : 'loss'))
        .html('<i class="fas fa-' + (pr >= 0 ? 'arrow-up' : 'arrow-down') + '"></i> ' + (pr >= 0 ? '+' : '-') + money(Math.abs(pr)) + ' &nbsp;(' + mg.toFixed(1) + '%)');
    }
    $p.add($s).on('input', calc); calc();
  }

  /* ---------- copy-to-clipboard on every 15-digit IMEI in tables ---------- */
  function copyText(t) {
    if (navigator.clipboard && window.isSecureContext !== false) return navigator.clipboard.writeText(t);
    var $t = $('<textarea>').val(t).css({ position: 'fixed', opacity: 0 }).appendTo('body'); $t[0].select();
    try { document.execCommand('copy'); } catch (e) {} $t.remove(); return Promise.resolve();
  }
  function decorateImei(root) {
    var re = /\b\d{15}\b/;
    $(root).find('td, .js-imei').each(function () {
      if ($(this).data('imeiDone') || $(this).find('input,select,textarea,.copy-imei').length) return;
      $(this).data('imeiDone', 1);
      $(this).contents().filter(function () { return this.nodeType === 3 && re.test(this.nodeValue); }).each(function () {
        var html = esc(this.nodeValue).replace(/\b(\d{15})\b/g, '<span class="imei">$1</span><button type="button" class="copy-imei" data-imei="$1" title="Copy IMEI"><i class="far fa-copy"></i></button>');
        $(this).replaceWith(html);
      });
    });
  }
  $(document).on('click', '.copy-imei', function (e) {
    e.preventDefault(); e.stopPropagation();
    var v = $(this).attr('data-imei');   // attr(), not data(): data() would turn 0123... into a number and drop the leading zero
    copyText(String(v)).then(function () { toast('IMEI copied: ' + v); });
  });

  /* ---------- ledger tables: debit/credit colours, running balance, money alignment ---------- */
  var RE_OUT = /(debit|udhaar|outflow|paid out|withdraw|expense|payment out|^payment$|^due$)/i,
      RE_IN  = /(credit|wasooli|inflow|received|deposit|payment in|^receipt$)/i,
      RE_BAL = /(running\s*balance|^balance$|closing)/i;
  function decorateLedgers(root) {
    $(root).find('table').each(function () {
      var $t = $(this), $ths = $t.find('thead tr').last().children('th');
      if (!$ths.length || $t.data('ledgerDone')) return;
      $t.data('ledgerDone', 1);
      var cols = {};
      $ths.each(function (i) {
        var h = $.trim($(this).text());
        if (RE_BAL.test(h)) cols[i] = 'bal';
        else if (RE_OUT.test(h) && !RE_IN.test(h)) cols[i] = 'out';
        else if (RE_IN.test(h) && !RE_OUT.test(h)) cols[i] = 'in';
      });
      if ($.isEmptyObject(cols)) return;
      $t.find('tbody tr').each(function () {
        var $tds = $(this).children('td');
        if ($tds.length !== $ths.length) return;
        $.each(cols, function (i, kind) {
          var $td = $tds.eq(i), txt = $.trim($td.text());
          if (!/[0-9]/.test(txt)) return;
          $td.addClass('money');
          if (kind === 'bal') { $td.addClass('running-balance').addClass(/^\s*-|\(/.test(txt) ? 'neg' : 'pos'); }
          else if (/[1-9]/.test(txt)) { $td.addClass(kind === 'out' ? 'amt-out' : 'amt-in'); }
        });
      });
    });
  }

  /* ---------- empty-state illustration ---------- */
  var EMPTY_SVG = '<svg viewBox="0 0 96 96" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="18" y="14" width="60" height="68" rx="8" fill="#f8fafc"/><path d="M32 34h32M32 46h32M32 58h20"/><circle cx="68" cy="68" r="12" fill="#fff" stroke="#94a3b8"/><path d="M64 68h8"/></svg>';
  function decorateEmpty(root) {
    $(root).find('table tbody').each(function () {
      var $r = $(this).children('tr');
      if ($r.length !== 1) return;
      var $td = $r.children('td');
      if ($td.length === 1 && ($td.attr('colspan') || 0) > 1 && /^\s*(no |nothing|empty|koi)/i.test($td.text()) && !$td.find('.empty-state').length) {
        $td.html('<div class="empty-state">' + EMPTY_SVG + '<div class="t">' + esc($.trim($td.text())) + '</div></div>');
      }
    });
  }

  /* ---------- date range presets (Today / Yesterday / This Week / This Month) ---------- */
  function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function initPresets() {
    $('form').each(function () {
      var $f = $(this), $from = $f.find('input[name="from"]'), $to = $f.find('input[name="to"]');
      if (!$from.length || !$to.length || $f.data('presets')) return;
      $f.data('presets', 1);
      var $box = $('<span class="date-presets no-print">' +
        '<button type="button" data-p="today">Today</button><button type="button" data-p="yesterday">Yesterday</button>' +
        '<button type="button" data-p="week">This Week</button><button type="button" data-p="month">This Month</button></span>');
      ($to.closest('.form-group, .col, [class*="col-"]').length ? $to.closest('.form-group, .col, [class*="col-"]') : $to.parent()).after($box);
      $box.on('click', 'button', function () {
        var n = new Date(), a = new Date(n), b = new Date(n), p = $(this).data('p');
        if (p === 'yesterday') { a.setDate(n.getDate() - 1); b = new Date(a); }
        if (p === 'week') { var dow = (n.getDay() + 6) % 7; a.setDate(n.getDate() - dow); }
        if (p === 'month') { a = new Date(n.getFullYear(), n.getMonth(), 1); }
        [[$from, a], [$to, b]].forEach(function (x) {
          if (x[0][0]._flatpickr) x[0][0]._flatpickr.setDate(ymd(x[1]), true); else x[0].val(ymd(x[1]));
        });
        $f[0].submit();
      });
    });
  }

  /* ---------- sidebar: hamburger toggle (desktop collapse / mobile drawer), state in localStorage ---------- */
  var SB_KEY = 'alnoor_sidebar', SB_BREAK = 992;
  function isMobileView() { return window.innerWidth < SB_BREAK; }
  function sbSave(v) { try { localStorage.setItem(SB_KEY, v); } catch (e) {} }
  function sbSaved() { try { return localStorage.getItem(SB_KEY); } catch (e) { return null; } }

  // When collapsed only icons show, so give every item a native hover tooltip (removed again when expanded)
  function sbTooltips(on) {
    $('#sidebar .nav-link, #sidebar .sidebar-brand').each(function () {
      var $a = $(this);
      if (on) {
        var t = $.trim($a.find('span').not('.arrow, .kbd-hint').first().text());
        var k = $.trim($a.find('.kbd-hint').text());
        if (t) $a.attr('title', t + (k ? ' (' + k + ')' : '')).attr('data-tt', '1');
      } else if ($a.attr('data-tt')) { $a.removeAttr('title').removeAttr('data-tt'); }
    });
  }
  function sbCollapse(collapsed, persist) {
    $('#sidebar').toggleClass('collapsed', collapsed);
    $('body').toggleClass('sidebar-collapsed', collapsed);
    $('#sidebarToggle').attr('aria-expanded', String(!collapsed));
    sbTooltips(collapsed);
    if (persist) sbSave(collapsed ? 'collapsed' : 'expanded');
  }
  function sbDrawer(open) {
    $('#sidebar').toggleClass('show', open);
    $('#sidebarBackdrop').toggleClass('show', open);
    $('body').toggleClass('sidebar-open', open);
    $('#sidebarToggle').attr('aria-expanded', String(open));
  }
  // Bring the layout in line with the viewport: drawer on mobile/tablet, saved collapsed state on desktop
  function sbApplyViewport() {
    if (isMobileView()) { sbCollapse(false, false); sbDrawer(false); }
    else { sbDrawer(false); sbCollapse(sbSaved() === 'collapsed', false); $('#sidebarToggle').attr('aria-expanded', String(sbSaved() !== 'collapsed')); }
  }
  $(document).on('click', '#sidebarToggle', function (e) {
    e.preventDefault();
    if (isMobileView()) sbDrawer(!$('#sidebar').hasClass('show'));
    else sbCollapse(!$('#sidebar').hasClass('collapsed'), true);
  });
  $(document).on('click', '#sidebarBackdrop', function () { sbDrawer(false); });
  // tapping a real page link inside the drawer closes it (submenu toggles stay open)
  $(document).on('click', '#sidebar a[href]:not([data-toggle])', function () { if (isMobileView()) sbDrawer(false); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape' && $('#sidebar').hasClass('show')) sbDrawer(false); });
  $(window).on('resize', debounce(sbApplyViewport, 120));

  /* ---------- boot ---------- */
  $(function () {
    tickClock(); setInterval(tickClock, 15000);
    sbApplyViewport();
    initGlobalSearch(); initImei(); initProfit(); initPresets();
    decorateImei(document); decorateLedgers(document); decorateEmpty(document);
    // keep working for rows injected later (modals, ajax)
    new MutationObserver(debounce(function () { decorateImei(document); decorateEmpty(document); }, 250))
      .observe(document.body, { childList: true, subtree: true });
  });
})(jQuery);
