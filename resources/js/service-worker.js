function redirectHtml(mirrorFullUrl) {
  var safe = mirrorFullUrl
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' + safe + '">'
    + '<script>location.replace(' + JSON.stringify(mirrorFullUrl) + ');</script></head><body><p>Перенаправление на зеркало…</p></body></html>';
}

/** Не уводим на зеркало админку, API и ЛК — только публичные страницы (конфиги, netcheck и т.д.). */
function skipMirrorFailoverForPath(pathname) {
  var p = pathname || '/';
  if (p.indexOf('/admin') === 0) return true;
  if (p.indexOf('/api') === 0) return true;
  if (p.indexOf('/personal') === 0) return true;
  if (p.indexOf('/livewire') === 0) return true;
  return false;
}

self.addEventListener('fetch', function (event) {
  if (!Array.isArray(MIRROR_ORIGINS) || MIRROR_ORIGINS.length === 0 || event.request.mode !== 'navigate') {
    return;
  }
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  // ЛК, админка и т.д.: пустой return без respondWith ломал в части браузеров POST-навигацию (формы → 405).
  if (skipMirrorFailoverForPath(url.pathname)) {
    event.respondWith(fetch(event.request));
    return;
  }
  const candidates = MIRROR_ORIGINS.filter(function (origin) {
    return origin && origin !== url.origin;
  });
  if (candidates.length === 0) {
    return;
  }
  const mirrorUrl = candidates[0] + url.pathname + url.search;

  event.respondWith(
    fetch(event.request).then(
      function (response) {
        if (response.status >= 500) {
          return new Response(redirectHtml(mirrorUrl), {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
          });
        }
        return response;
      },
      function () {
        return new Response(redirectHtml(mirrorUrl), {
          status: 200,
          headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
      }
    )
  );
});
