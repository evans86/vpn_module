'use strict';
const MIRROR_ORIGIN = @json($mirrorOrigin);

function redirectHtml(mirrorFullUrl) {
  var safe = mirrorFullUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' + safe + '"><script>location.replace(' + JSON.stringify(mirrorFullUrl) + ');</script></head><body><p>Перенаправление на зеркало…</p></body></html>';
}

self.addEventListener('fetch', function (event) {
  if (!MIRROR_ORIGIN || event.request.mode !== 'navigate') {
    return;
  }
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  const mirrorUrl = MIRROR_ORIGIN + url.pathname + url.search;

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
