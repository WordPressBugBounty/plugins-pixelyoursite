# Local deviations from upstream

Vendored from `facebook/capi-param-builder-php` **1.3.1**
(repo `facebook/capi-param-builder`, ref `66a7eb8`).

This copy is **not** a verbatim upstream tree and it was **not** produced by php-scoper. Four
changes are applied on top of the namespace prefix. If you update the library, re-apply all four
— each one is load-bearing, and three of them fail *silently* if forgotten. There is also one
file that must simply be *carried across* rather than changed: see §5, the `LICENSE`.

The re-vendoring script automates this and prints what it applied. It lives **outside the plugin**,
in the development checkout at `tools/vendor-param-builder.js` alongside the checks, so that
nothing in a release depends on it. That script is a convenience; this file is the record, so the
knowledge survives the script being deleted.

## 1. Namespace prefix

`namespace FacebookAds;` → `namespace PYS_PRO_GLOBAL\FacebookAds;` in all 17 files, and the one
`use FacebookAds\PlainDataObject;` alongside it. Same prefix as the rest of `vendor_prefix/`,
including in the free plugin.

## 2. `define()` bound to `__NAMESPACE__`

19 constants — `FB_PREFIX`, `FBP_NAME`, `FBC_NAME`, `FBI_NAME`, `FBCLID`, `LANGUAGE_TOKEN`,
`DEFAULT_FORMAT`, `DEFAULT_1PC_AGE`, `APPENDIX_*`, `SUPPORTED_LANGUAGES_TOKEN` and the rest — are
declared upstream with a bare `define('NAME', …)`.

**`define()` creates a global constant regardless of the enclosing namespace.** Only `const` gets
namespaced. So an unmodified copy publishes `FB_PREFIX` and friends into the global constant
space: any other plugin shipping this library collides with us, PHP emits *"Constant already
defined"*, and whichever plugin loaded first silently wins for everyone.

They are rewritten to `define(__NAMESPACE__ . '\\NAME', …)`. Unqualified references inside the
library still resolve — PHP looks in the current namespace before the global one — while the
names become unreachable from outside.

Check after updating:

```php
defined( 'FBC_NAME' );                                  // must be false
defined( 'PYS_PRO_GLOBAL\FacebookAds\FBC_NAME' );       // must be true
```

## 3. `require_once` made absolute

`src/ParamBuilder.php` requires seven files by include-path-relative name
(`require_once 'model/Constants.php';`). That resolves only because PHP falls back to the calling
file's directory, and it breaks under some opcache and `include_path` configurations. Rewritten to
`require_once __DIR__ . '/...'`. Every other file upstream already used `__DIR__`.

## 4. `composer.json` is kept, and `VersionProvider` is memoised

**Do not strip `composer.json` from this package.** The rest of `vendor_prefix/` ships `src/`
only, but `src/util/VersionProvider.php` reads `<pkg>/composer.json` at runtime to get the
version, and `AppendixProvider` encodes that version into the 8-character appendix. With the file
missing the version resolves to `""` and the appendix **silently degrades** to the legacy
2-character `AQ` token: the library still works, Meta still accepts the events, and the token
changes shape without anything complaining.

An earlier version of this note called that token *"the attribution that is most of the reason to
adopt the library"*. **That was wrong, and it matters here because it was the argument for keeping
`composer.json`.** Read `src/util/AppendixProvider.php`: it packs six bytes — `DEFAULT_FORMAT`,
`LANGUAGE_TOKEN_INDEX`, an is-new byte, and this library's own `major.minor.patch`. Nothing in it
identifies a partner, vendor or site, and every PHP site on the same version emits a byte-identical
token. The field that does identify us, `partner_agent: "dvpixelyoursite"`, is already sent on
every event independently of this library. So keep `composer.json` in order to keep the vendored
copy faithful and the version legible — not because attribution depends on it.

Note the release archive's ignore list contains a `composer.json` entry. It matches the root file
only, not this one — verified by building the zip and listing its contents. If that ignore pattern
is ever changed to `**/composer.json`, this breaks *in the release only* while working perfectly
in development. Re-check it if you touch `archive.config.js`.

`VersionProvider::getVersion()` is also memoised, because upstream re-reads and re-parses that
file on every call and `AppendixProvider` calls it four times inside the `ParamBuilder`
constructor alone.

Check after updating:

```php
PYS_PRO_GLOBAL\FacebookAds\VersionProvider::getVersion();   // must be the real version, not ""
// and the appendix must be 8 characters, not 2
```

## 5. `LICENSE` must be carried across

Not a deviation — a correction. The first vendoring of this package **omitted the `LICENSE`
file**, while all 17 source files carry the header *"licensed under the license found in the
LICENSE file in the root directory of this source tree"*. Those headers pointed at nothing, in a
plugin distributed on wordpress.org.

The file is Meta's **Facebook Platform License**, not GPL: it grants use "in connection with the
web services and APIs provided by Facebook" and binds use to the Facebook Platform Policy. That
field-of-use restriction is the reason it matters that the text actually ships — a reader of the
released plugin otherwise has no way to know which licence this subtree is under. Whether
bundling it alongside GPL code is acceptable is a product and legal question, not one this file
settles; `vendor_prefix/facebook/php-business-sdk` has shipped under the same licence for years,
so this is existing practice rather than a new decision. It just has to be *stated*.

Where to find it when re-vendoring: the source is the polyglot monorepo, whose PHP package sits
at `php/capi-param-builder/`, so `<src-pkg>` is normally that subdirectory and the `LICENSE` is
one or two levels **above** it. Upstream keeps identical copies at the repo root and at
`php/LICENSE`. The re-vendoring script searches `LICENSE`, `php/LICENSE`, `../LICENSE`
and `../../LICENSE`, and **aborts** if it finds none — a warning is how the file went missing the
first time. `--allow-missing-license` overrides that and says so out loud.

The release archive does not strip it: `archive.config.js`'s `'*.md'` and `'composer.json'`
patterns are root-only, so everything in this directory reaches end users — including, incidentally,
this file.

## What we deliberately do not use

`getClientIpAddress()`, `getEventSourceUrl()` and `getReferrerUrl()` return their values with the
appendix appended — `203.0.113.10.AQEAAQMB` is not an IP, and the event source URL comes back with
the token glued on and its query string intact. Our own resolvers are also better: real-IP
detection behind Cloudflare/Akamai, URL-parameter stripping, the order-confirmation override.
`getNormalizedAndHashedPII()` is redundant — the Business SDK's `UserData::normalize()` already
normalises and hashes every field, idempotently.
