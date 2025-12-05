// Service Worker для страницы netcheck - обеспечивает офлайн работу
const CACHE_NAME = 'netcheck-v1';
const urlsToCache = [
  '/netcheck',
  '/netcheck/',
];

// Установка Service Worker
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Cache opened');
        // Пытаемся кэшировать страницу, но не блокируем установку при ошибке
        return Promise.allSettled(
          urlsToCache.map(url => {
            return fetch(url, {credentials: 'same-origin', cache: 'no-cache'})
              .then(response => {
                if (response.ok) {
                  return cache.put(url, response);
                }
              })
              .catch(err => {
                console.log('Failed to cache:', url, err);
                // Не прерываем установку при ошибке
              });
          })
        );
      })
      .catch((error) => {
        console.log('Service Worker: Cache install failed:', error);
        // Не прерываем установку при ошибке
      })
      .then(() => {
        console.log('Service Worker: Installation complete');
      })
  );
  // Активируем Service Worker сразу, не ждем перезагрузки страницы
  self.skipWaiting();
});

// Активация Service Worker
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // Берем контроль над всеми страницами сразу
  return self.clients.claim();
});

// Перехват запросов
self.addEventListener('fetch', (event) => {
  // Обрабатываем только GET запросы
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  
  // Для страницы netcheck используем стратегию "сначала кэш, потом сеть"
  if (url.pathname === '/netcheck' || url.pathname === '/netcheck/') {
    event.respondWith(
      caches.match(event.request)
        .then((cachedResponse) => {
          // Если есть в кэше - возвращаем из кэша
          if (cachedResponse) {
            // Пытаемся обновить кэш в фоне, если есть сеть
            fetch(event.request)
              .then((response) => {
                if (response.ok) {
                  caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, response.clone());
                  });
                }
              })
              .catch(() => {
                // Игнорируем ошибки обновления
              });
            return cachedResponse;
          }

          // Если нет в кэше - пытаемся загрузить из сети
          return fetch(event.request)
            .then((response) => {
              // Проверяем валидность ответа
              if (!response || response.status !== 200) {
                return response;
              }

              // Клонируем ответ для кэширования
              const responseToCache = response.clone();

              // Кэшируем ответ
              caches.open(CACHE_NAME)
                .then((cache) => {
                  cache.put(event.request, responseToCache);
                });

              return response;
            })
            .catch(() => {
              // Если сеть недоступна - возвращаем кэшированную версию или ошибку
              return caches.match('/netcheck') || 
                     caches.match('/netcheck/') ||
                     new Response('Страница недоступна офлайн. Пожалуйста, посетите страницу с интернетом для кэширования.', {
                       status: 503,
                       statusText: 'Service Unavailable',
                       headers: new Headers({
                         'Content-Type': 'text/html; charset=utf-8'
                       })
                     });
            });
        })
    );
    return;
  }

  // Для других запросов используем стратегию "сначала сеть, потом кэш"
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Если запрос успешен - кэшируем и возвращаем
        if (response && response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });
        }
        return response;
      })
      .catch(() => {
        // Если сеть недоступна - пытаемся вернуть из кэша
        return caches.match(event.request);
      })
  );
});

