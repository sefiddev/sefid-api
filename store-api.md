# Sefid store API

> The full, current guide for outside teams, in Persian: [`sefid-api.md`](sefid-api.md) (§10 is this contract). This file stays as the English summary.

For a shop that is not on WooCommerce and wants Sefid to ask it live instead of keeping its products in Sefid, or wants
Sefid to read a signed-in customer's orders, cart and addresses and start a checkout. WooCommerce shops get all of this
from the plugin (3.2.0+); nothing to build.

The owner puts the base address in the panel (Products › «آدرس API فروشگاه شما»), for example
`https://shop.example/sefid`. Sefid then sends `POST <base>/<op>` with a JSON body.

## Every request

Signed with the site's secret key exactly like the plugin's calls (`examples/sign.js`, `examples/sign.php`):

| header | value |
|---|---|
| `x-sefid-key` | the site's public key |
| `x-sefid-timestamp` | milliseconds since 1970; refuse anything more than 5 minutes off |
| `x-sefid-nonce` | a random id; refuse one you have seen in the last 5 minutes |
| `x-sefid-signature` | `hex(HMAC-SHA256(secret, "POST\n" + path + "\n" + timestamp + "\n" + nonce + "\n" + hex(SHA256(body))))`, where `path` is your URL's path (`/sefid/cart/add`) |
| `x-sefid-identity` | only for a signed-in customer: `base64url(json).hex(HMAC-SHA256(secret, base64url(json)))`; json has `loggedIn`, `phone`, `name`, `externalId` (your own user id, as your site gave it to the widget), `iat`, `exp` (five minutes) |

Answer JSON with HTTP 200, `{"ok": true, ...}`, or `{"ok": false, "error": "a_short_reason"}` for a refusal. Answer within
8 seconds. A timeout, a 5xx or a 429 is tried again, three times in all; after that the chatbot tells the customer the
service is not reachable right now. Every call is listed under its chat in the owner's panel.

## Ops

Products (only when the owner chose «با درخواست زنده»):
- `products/search` `{"q": "...", "limit": 10}` → `{"products": [Product]}`; optional since 2026-11-06 (ADR-0116): `author`,
  `translator`, `publisher`, `category`, `sort` (`price_asc` | `price_desc` | `newest` | `bestselling`), `inStock`; the answer may
  add `person` `{"name", "role", "url", "books"}` and `categories`. A shop that ignores them answers as before.
- `people/search` `{"q": "a writer's or translator's name"}` → `{"people": [{"name", "role", "url", "books"}]}`
- `products/get` `{"id": "..."}` → `{"products": [Product]}`

`Product`: `{"id", "title", "price", "salePrice"?, "inStock", "stockQty"?, "url", "image"?, "categories"?: [string],
"description"?, "variations"?: [{"id", "attributes", "price", "inStock"}]}`; URLs HTTPS. Public data only.

The customer's own (each turned on by the Sefid team by agreement; `x-sefid-identity` always present):
- `orders/mine` `{"limit": 10}` → `{"orders": [{"number", "date", "status", "total", "currency", "items", "trackingCode"?}]}`
- `cart/get` `{}` → `Cart`
- `cart/add` `{"productId", "variationId"?, "quantity"}` → `Cart`
- `cart/remove` `{"lineId"}` → `Cart`
- `cart/clear` `{}` → `Cart`
- `addresses/list` `{}` → `{"addresses": [{"id", "label", "city", "address", "postcode", "recipient"}]}`
- `addresses/add` `{"fields": {"label"?, "city", "address", "postcode"?, "recipient"?, "phone"?}}` → the list again
- `checkout/start` `{"addressId", "coupon"?, "options"?: {...}, "sefidConversation"}` → `{"orderId", "total", "currency", "payUrl"}`

`Cart`: `{"lines": [{"lineId", "productId", "name", "quantity", "price", "total"}], "total", "currency"}`. The cart is
yours (the one the customer sees on your site); Sefid keeps no copy.

## The payment result

When an order made by `checkout/start` is paid or fails, tell Sefid so the result lands in the same chat: a signed
`POST https://sefid.dev/api/ingest/payment-result` (same headers, path `/api/ingest/payment-result`) with
`{"orderId", "status": "paid" | "failed", "sefidConversation": <what checkout/start received>, "link"?: <pay again>}`.
