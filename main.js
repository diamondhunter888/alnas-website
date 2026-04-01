// ── NAVBAR SCROLL ──
const nav = document.getElementById('nav');
if (nav) {
  if (!document.body.classList.contains('page-subpage')) {
    window.addEventListener('scroll', () => nav.classList.toggle('scrolled', scrollY > 40), { passive: true });
  }
}

// ── SCROLL REVEAL ──
const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
}, { threshold: 0, rootMargin: '0px 0px -10px 0px' });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));

// ── MOBILE NAV ──
const burger = document.querySelector('.n-burger');
const mobileNav = document.getElementById('nMobile');
const navEl = document.querySelector('nav');
if (burger && mobileNav && navEl) {
  burger.addEventListener('click', () => {
    const open = navEl.classList.toggle('open');
    mobileNav.classList.toggle('show', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  mobileNav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    navEl.classList.remove('open');
    mobileNav.classList.remove('show');
    burger.setAttribute('aria-expanded', 'false');
  }));
}

// ── FAQ ACCORDION ──
document.querySelectorAll('.ftrig').forEach(btn => {
  btn.addEventListener('click', () => {
    const item = btn.closest('.fi');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.fi.open').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});

// ── COOKIE BANNER ──
(function () {
  var KEY = 'alnas_cookie_consent';
  var banner = document.getElementById('cb-banner');
  var overlay = document.getElementById('cb-overlay');
  var modal = document.getElementById('cb-modal');
  if (!banner || !overlay || !modal) return;
  function getConsent() { try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; } }
  function save(a, p) { localStorage.setItem(KEY, JSON.stringify({ necessary: true, analytics: !!a, preferences: !!p, ts: new Date().toISOString() })); }
  function hideBanner() { banner.classList.remove('cb-show'); }
  function showBanner() { banner.classList.add('cb-show'); }
  window.cbOpenModal = function () {
    var c = getConsent();
    document.getElementById('cb-analytics').checked = c ? !!c.analytics : false;
    document.getElementById('cb-prefs').checked = c ? !!c.preferences : false;
    overlay.classList.add('cb-show');
    modal.classList.add('cb-show');
  };
  function closeModal() { overlay.classList.remove('cb-show'); modal.classList.remove('cb-show'); }
  if (!getConsent()) { setTimeout(showBanner, 700); }
  document.getElementById('cbAccept').onclick = function () { save(true, true); hideBanner(); };
  document.getElementById('cbReject').onclick = function () { save(false, false); hideBanner(); };
  document.getElementById('cbPref').onclick = cbOpenModal;
  document.getElementById('cbModalReject').onclick = function () { save(false, false); closeModal(); hideBanner(); };
  document.getElementById('cbModalSave').onclick = function () { save(document.getElementById('cb-analytics').checked, document.getElementById('cb-prefs').checked); closeModal(); hideBanner(); };
  overlay.onclick = closeModal;
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
