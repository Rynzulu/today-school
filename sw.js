/**
 * Copyright 2018 Google Inc. All Rights Reserved.
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *     http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// If the loader is already loaded, just stop.
if (!self.define) {
  let registry = {};

  // Used for `eval` and `importScripts` where we can't get script URL by other means.
  // In both cases, it's safe to use a global var because those functions are synchronous.
  let nextDefineUri;

  const singleRequire = (uri, parentUri) => {
    uri = new URL(uri + ".js", parentUri).href;
    return registry[uri] || (
      
        new Promise(resolve => {
          if ("document" in self) {
            const script = document.createElement("script");
            script.src = uri;
            script.onload = resolve;
            document.head.appendChild(script);
          } else {
            nextDefineUri = uri;
            importScripts(uri);
            resolve();
          }
        })
      
      .then(() => {
        let promise = registry[uri];
        if (!promise) {
          throw new Error(`Module ${uri} didn’t register its module`);
        }
        return promise;
      })
    );
  };

  self.define = (depsNames, factory) => {
    const uri = nextDefineUri || ("document" in self ? document.currentScript.src : "") || location.href;
    if (registry[uri]) {
      // Module is already loading or loaded.
      return;
    }
    let exports = {};
    const require = depUri => singleRequire(depUri, uri);
    const specialDeps = {
      module: { uri },
      exports,
      require
    };
    registry[uri] = Promise.all(depsNames.map(
      depName => specialDeps[depName] || require(depName)
    )).then(deps => {
      factory(...deps);
      return exports;
    });
  };
}
define(['./workbox-43e44e5a'], (function (workbox) { 'use strict';

  self.skipWaiting();
  workbox.clientsClaim();
  /**
   * The precacheAndRoute() method efficiently caches and responds to
   * requests for URLs in the manifest.
   * See https://goo.gl/S9QRab
   */
  workbox.precacheAndRoute([{
    "url": "pwa-maskable-512x512.png",
    "revision": "8321ced8dc79005a90967ff2fc7193f8"
  }, {
    "url": "pwa-512x512.png",
    "revision": "1bb79cadb5bc289ac288c3fa50401dc3"
  }, {
    "url": "pwa-192x192.png",
    "revision": "3f28b3db3ace67bc54482cbd09173767"
  }, {
    "url": "index.html",
    "revision": "3302cdfca8a6577eff94b29bd1da54bd"
  }, {
    "url": "icon.svg",
    "revision": "06850529047c8f594f84c7b27d24f8d8"
  }, {
    "url": "favicon.ico",
    "revision": "f774dcb7b4a0e4a2d3a3aceebf593916"
  }, {
    "url": "apple-touch-icon.png",
    "revision": "8fab62a515e122f1f322b513979322e1"
  }, {
    "url": "assets/purify.es-Dn3VvdGh.js",
    "revision": null
  }, {
    "url": "assets/index.es-Cczn2wXk.js",
    "revision": null
  }, {
    "url": "assets/index-rZGFnGZ0.css",
    "revision": null
  }, {
    "url": "assets/index-QxuQxqpI.js",
    "revision": null
  }, {
    "url": "apple-touch-icon.png",
    "revision": "8fab62a515e122f1f322b513979322e1"
  }, {
    "url": "favicon.ico",
    "revision": "f774dcb7b4a0e4a2d3a3aceebf593916"
  }, {
    "url": "icon.svg",
    "revision": "06850529047c8f594f84c7b27d24f8d8"
  }, {
    "url": "pwa-192x192.png",
    "revision": "3f28b3db3ace67bc54482cbd09173767"
  }, {
    "url": "pwa-512x512.png",
    "revision": "1bb79cadb5bc289ac288c3fa50401dc3"
  }, {
    "url": "pwa-maskable-512x512.png",
    "revision": "8321ced8dc79005a90967ff2fc7193f8"
  }, {
    "url": "manifest.webmanifest",
    "revision": "01f25a881e6cf572d61f357e9cf32bed"
  }], {});
  workbox.cleanupOutdatedCaches();
  workbox.registerRoute(new workbox.NavigationRoute(workbox.createHandlerBoundToURL("index.html"), {
    denylist: [/\/api\//, /\/backend-php\//, /\.php/i]
  }));
  workbox.registerRoute(({
    url
  }) => /\/(?:api|backend-php)\//i.test(url.pathname) || /\.php$/i.test(url.pathname), new workbox.NetworkOnly(), 'GET');
  workbox.registerRoute(({
    url
  }) => /\/(?:api|backend-php)\//i.test(url.pathname) || /\.php$/i.test(url.pathname), new workbox.NetworkOnly(), 'POST');
  workbox.registerRoute(/^https:\/\/fonts\.googleapis\.com\/.*/i, new workbox.CacheFirst({
    "cacheName": "google-fonts-cache",
    plugins: [new workbox.ExpirationPlugin({
      maxEntries: 10,
      maxAgeSeconds: 31536000
    }), new workbox.CacheableResponsePlugin({
      statuses: [0, 200]
    })]
  }), 'GET');
  workbox.registerRoute(/^https:\/\/fonts\.gstatic\.com\/.*/i, new workbox.CacheFirst({
    "cacheName": "gstatic-fonts-cache",
    plugins: [new workbox.ExpirationPlugin({
      maxEntries: 10,
      maxAgeSeconds: 31536000
    }), new workbox.CacheableResponsePlugin({
      statuses: [0, 200]
    })]
  }), 'GET');

}));
