/*
 * gkc 行政書士開業キット 公式ミニサイト カスタム計測
 * - GA4 (gtag.js) 前提。測定ID: G-G7CDRQFBH6
 * - 各HTMLの <head> で gtag.js 本体を読み込み済みのこと
 * - 本ファイルは </body> 直前で読み込む
 */
(function () {
  'use strict';

  if (typeof window.gtag !== 'function') {
    // gtag未ロード時は何もしない
    return;
  }

  var host = location.hostname;
  var path = location.pathname;

  // ---------- ページ分類 ----------
  var pageType = 'other';
  if (/\/gkc\/?$/.test(path) || /\/gkc\/index\.html$/.test(path)) pageType = 'top';
  else if (/\/gkc\/lp\.html$/.test(path)) pageType = 'lp';
  else if (/\/gkc\/about\/?$/.test(path)) pageType = 'about';
  else if (/\/gkc\/articles\/?$/.test(path)) pageType = 'article_index';
  else if (/\/gkc\/articles\/.+\.html$/.test(path)) pageType = 'article';

  var bodyEl = document.body || document.documentElement;
  var articleCategory = bodyEl.getAttribute('data-category') || '';
  var articleId = bodyEl.getAttribute('data-article-id') || '';

  // page_view の強化：page_type / article_category / article_id を付ける
  try {
    window.gtag('event', 'page_view_enhanced', {
      page_type: pageType,
      article_category: articleCategory,
      article_id: articleId
    });
  } catch (e) {}

  // ---------- 1. スクロール深度 ----------
  var depthMarks = [25, 50, 75, 100];
  var depthFired = {};
  function onScroll() {
    var doc = document.documentElement;
    var body = document.body;
    var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
    var winH = window.innerHeight || doc.clientHeight;
    var docH = Math.max(
      body.scrollHeight, doc.scrollHeight,
      body.offsetHeight, doc.offsetHeight,
      body.clientHeight, doc.clientHeight
    );
    var denom = Math.max(docH - winH, 1);
    var pct = Math.round((scrollTop / denom) * 100);
    for (var i = 0; i < depthMarks.length; i++) {
      var m = depthMarks[i];
      if (!depthFired[m] && pct >= m) {
        depthFired[m] = true;
        window.gtag('event', 'scroll_depth', {
          percent: m,
          page_type: pageType,
          article_category: articleCategory,
          article_id: articleId
        });
      }
    }
  }
  var scrollTimer = null;
  window.addEventListener('scroll', function () {
    if (scrollTimer) return;
    scrollTimer = setTimeout(function () {
      scrollTimer = null;
      onScroll();
    }, 200);
  }, { passive: true });
  // 初回計算
  onScroll();

  // ---------- 2. 滞在時間 ----------
  var timeMarks = [
    { sec: 30, fired: false, label: '30s' },
    { sec: 60, fired: false, label: '1m' },
    { sec: 180, fired: false, label: '3m' }
  ];
  timeMarks.forEach(function (t) {
    setTimeout(function () {
      if (t.fired) return;
      t.fired = true;
      window.gtag('event', 'time_on_page', {
        threshold: t.label,
        seconds: t.sec,
        page_type: pageType,
        article_category: articleCategory,
        article_id: articleId
      });
    }, t.sec * 1000);
  });

  // ---------- 3. クリック計測 ----------
  function ctaLabel(a) {
    // data-cta-label > 可視テキスト > href
    var lbl = a.getAttribute('data-cta-label');
    if (lbl) return lbl;
    var txt = (a.textContent || '').replace(/\s+/g, ' ').trim();
    return txt || a.getAttribute('href') || 'unknown';
  }

  document.addEventListener('click', function (ev) {
    var target = ev.target;
    // aタグを遡って取得
    while (target && target !== document.body && target.nodeName !== 'A') {
      target = target.parentNode;
    }
    if (!target || target.nodeName !== 'A') return;
    var href = target.getAttribute('href') || '';
    if (!href) return;

    // ---- CTA判定 ----
    var cls = target.className || '';
    var isCta =
      /\bcta-primary\b|\bcta-secondary\b|\bcta-button\b|\bnav-cta\b|\blp-cta-btn\b/.test(cls) ||
      target.hasAttribute('data-cta');

    if (isCta) {
      window.gtag('event', 'cta_click', {
        cta_label: ctaLabel(target),
        cta_href: href,
        cta_class: cls,
        page_type: pageType,
        article_category: articleCategory,
        article_id: articleId
      });
    }

    // ---- 外部リンク判定 ----
    var isExternal = false;
    try {
      var url = new URL(href, location.href);
      if (url.hostname && url.hostname !== host) isExternal = true;
    } catch (e) {
      // 相対URL・hash等は外部扱いしない
    }
    if (isExternal) {
      window.gtag('event', 'outbound_click', {
        link_url: href,
        link_text: (target.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 80),
        page_type: pageType,
        article_category: articleCategory,
        article_id: articleId
      });
    }

    // ---- 内部リンクの種類別計測（記事一覧→記事、記事→lpなど） ----
    if (!isExternal && /\.html($|\?|#)/.test(href)) {
      var destType = 'other';
      if (/lp\.html/.test(href)) destType = 'lp';
      else if (/articles\/[^\/]+\.html/.test(href)) destType = 'article';
      else if (/about\//.test(href)) destType = 'about';
      window.gtag('event', 'internal_nav', {
        from_type: pageType,
        to_type: destType,
        link_href: href
      });
    }
  }, true);

  // ---------- 4. 記事読了（下端到達） ----------
  if (pageType === 'article') {
    var readFired = false;
    window.addEventListener('scroll', function () {
      if (readFired) return;
      var doc = document.documentElement;
      var body = document.body;
      var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
      var winH = window.innerHeight || doc.clientHeight;
      var docH = Math.max(body.scrollHeight, doc.scrollHeight);
      if (scrollTop + winH >= docH - 50) {
        readFired = true;
        window.gtag('event', 'article_read_complete', {
          article_category: articleCategory,
          article_id: articleId
        });
      }
    }, { passive: true });
  }

  // ---------- 5. 離脱前イベント（engagement結果の補助） ----------
  var pageEnter = Date.now();
  window.addEventListener('beforeunload', function () {
    var stay = Math.round((Date.now() - pageEnter) / 1000);
    try {
      window.gtag('event', 'page_exit', {
        stay_seconds: stay,
        page_type: pageType,
        article_category: articleCategory,
        article_id: articleId
      });
    } catch (e) {}
  });
})();
