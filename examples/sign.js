/* eslint-disable @typescript-eslint/no-require-imports -- CommonJS script (Node), import syntax not applicable */
/**
 * نمونه‌ی امضای سفید (Node ≥ 18). منطق دقیقا با لایه‌ی سفید (signing.ts) یکسان است.
 *
 *   bun sign.js
 *
 * در پروداکشن از متغیرهای محیطی برای کلیدها استفاده کنید.
 */
const crypto = require("crypto");

const PUBLIC_KEY = process.env.SEFID_PUBLIC_KEY || "pk_xxx";
const SECRET_KEY = process.env.SEFID_SECRET_KEY || "sk_xxx";
const API_BASE = process.env.SEFID_API_BASE || "https://sefid.dev";

function b64url(buf) {
  return Buffer.from(buf).toString("base64").replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}
function sha256Hex(s) {
  return crypto.createHash("sha256").update(s).digest("hex");
}
function hmacHex(secret, data) {
  return crypto.createHmac("sha256", secret).update(data).digest("hex");
}

/** توکن هویت برای دادن به ویجت (کاربر واردشده‌ی سایت شما). */
function buildIdentityToken(payload) {
  const body = b64url(JSON.stringify(payload));
  return `${body}.${hmacHex(SECRET_KEY, body)}`;
}

/** امضا + ارسال یک درخواست سرور-به-سرور به سفید. path مثل "/api/ingest/products". */
async function signedPost(path, bodyObj) {
  const body = JSON.stringify(bodyObj);
  const timestamp = String(Date.now());
  const nonce = crypto.randomUUID();
  const canonical = ["POST", path, timestamp, nonce, sha256Hex(body)].join("\n");
  const signature = hmacHex(SECRET_KEY, canonical);

  const res = await fetch(API_BASE + path, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Sefid-Key": PUBLIC_KEY,
      "X-Sefid-Timestamp": timestamp,
      "X-Sefid-Nonce": nonce,
      "X-Sefid-Signature": signature,
    },
    body,
  });
  return { status: res.status, json: await res.json().catch(() => null) };
}

// ── نمونه ──
async function main() {
  const now = Math.floor(Date.now() / 1000);
  const token = buildIdentityToken({
    loggedIn: true,
    externalId: "123",
    name: "سارا",
    phone: "09120000000",
    iat: now,
    exp: now + 30 * 24 * 3600,
  });
  console.log("identityToken:", token);

  // upsert یک محصول
  const out = await signedPost("/api/ingest/products", {
    products: [
      {
        externalId: "SKU-1",
        name: "شومیز مشکی",
        price: 620000,
        inStock: true,
        stockQty: 10,
        imageUrl: "https://site/img.jpg",
        productUrl: "https://site/p/shomiz",
        shippable: true,
        categories: ["پوشاک"],
        variations: [{ attributes: { size: "M" }, price: 620000, inStock: true }],
      },
    ],
  });
  console.log("ingest:", out.status, out.json);
}

if (require.main === module) {
  main().catch((e) => {
    console.error(e);
    process.exit(1);
  });
}

module.exports = { buildIdentityToken, signedPost };
