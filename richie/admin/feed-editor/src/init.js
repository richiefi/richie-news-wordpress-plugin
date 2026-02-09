/**
 * Initialize apiFetch middleware before the app loads
 */

import apiFetch from '@wordpress/api-fetch';

// Ensure the root URL middleware is set up
if (window.wpApiSettings && window.wpApiSettings.root) {
  apiFetch.use(apiFetch.createRootURLMiddleware(window.wpApiSettings.root));
}

// Ensure the nonce middleware is set up
if (window.wpApiSettings && window.wpApiSettings.nonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(window.wpApiSettings.nonce));
}
