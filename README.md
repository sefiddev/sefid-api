# Sefid API

The public guide for connecting a website to [Sefid](https://sefid.dev), the AI chat assistant: the on-page widget, server-to-server sync, signed identity, webhooks and the live store API.

راهنمای کامل اتصال به سفید، برای تیم فنی هر سایت.

## What is here

| File | What it is |
|---|---|
| [`sefid-api.md`](sefid-api.md) | The full guide (Persian): keys, signing, widget, sync, identity, webhooks, chat API, store API, errors and limits. |
| [`sefid-api.openapi.json`](sefid-api.openapi.json) | The same API as an OpenAPI description, for code generators and API tools. |
| [`store-api.md`](store-api.md) | English summary of the live store contract (for shops not on WooCommerce). |
| [`examples/sign.js`](examples/sign.js), [`examples/sign.php`](examples/sign.php) | How to sign requests and identity tokens with HMAC-SHA256, in Node.js and PHP. |

Everything works from any language: HTTPS, JSON and HMAC-SHA256. Your secret key stays on your server; nothing secret is ever sent to a browser.

WordPress and WooCommerce sites do not need any of this: install the plugin, [sefid-wp-kit](https://github.com/sefiddev/sefid-wp-kit).

## Keys

Get your public key and secret key from your Sefid panel at [sefid.dev](https://sefid.dev).

## License

The examples are under the MIT license (see [`LICENSE`](LICENSE)). The guide may be read, copied and shared freely.
