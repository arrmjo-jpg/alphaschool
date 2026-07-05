# Media Architecture

`App\Modules\Media` (docs/DOMAIN_BLUEPRINT.md §12, Addendum B3, Addendum D4) is the Foundation-tier module responsible for file storage: disk tiers, physical path generation, sensitivity classification, and private-file serving. Sprint 1.2.1 builds the skeleton — no real collections or consumers exist yet; those arrive with People in Phase 2.

## Three disks, not one per category

`config/filesystems.php` defines exactly three logical disks: `public` (CDN-fronted), `private` (never publicly reachable), `temporary` (ephemeral, lifecycle-purged). Application code must only ever reference these three names (`Storage::disk('public'|'private'|'temporary')`), never a concrete driver — each disk's driver is env-controlled (`local` for dev, `s3` for Cloudflare R2 in production, since R2 is S3-API-compatible and needs no custom driver). Swapping backends is a config change, not a code change.

Disks represent physical storage and serving mechanism only, never fine-grained authorization — see "Sensitivity classification" below for the actual authorization axis.

## Sensitivity classification, not a fourth disk

Within `private`, `Media::sensitivity` (`standard`/`high`) classifies collections per Addendum B3. High-sensitivity collections (medical reports, court documents, identity documents) are meant to get mandatory view/download audit logging and a dedicated Policy once real consumers exist — `MediaPolicy::view()` is currently an explicit, documented placeholder that allows any authenticated user, since no real permission model (Roles/Permissions, Phase 2 Identity) exists yet to gate against. This **must** be replaced before a high-sensitivity collection ships for real.

## Path scheme

`App\Modules\Media\Support\AlphaSchoolPathGenerator` implements the literal scheme from the Blueprint and Playbook:

```
{tier}/{branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}
```

- `{tier}` is the media's own disk name (`public`/`private`/`temporary`) — kept as a literal path segment even though each disk also has its own root, so the physical layout doesn't implicitly assume tiers will never share a backend.
- `{branch_id}` is present only for models implementing `App\Modules\Media\Contracts\HasBranchScopedMedia` and returning a non-null branch ID. Global entities (e.g. a Guardian's media, per Branch Ownership B6) get no branch folder at all — never an empty placeholder segment.
- `{media_id}` is `Media`'s own primary key, which is a ULID (see below), not the owning model's ID.

The interface lives in Media's own namespace, not Core — "branch" is a legitimate Foundation-level concept (already used throughout Identity/People), but Core itself must stay domain-agnostic and never reference it, even indirectly through an interface name.

## Media's primary key is a ULID (Addendum D4's deliberate exception)

Every other domain aggregate uses the dual-ID scheme: an internal auto-increment integer as the real primary key (cheap joins), plus a separate `public_id` ULID column for external API representation. `Media` is the one named exception — its primary key **is** the ULID directly (`App\Modules\Media\Models\Media` uses Laravel's `HasUlids` trait; `database/migrations/2026_07_01_064044_create_media_table.php` defines `id` as `ulid()->primary()`), rather than adding a third identifier. Media is never joined transitively across other tables in hot-path queries the way Person/Enrollment are, so the join-performance argument for keeping the PK a cheap integer doesn't apply here.

This is unrelated to Spatie's own `uuid` column on the `media` table (a v4 UUID used internally by Media Library Pro's JS uploader components for keying in-progress uploads) — that column stays untouched; it is Spatie's own mechanism, not this application's public-identifier convention.

## Private files: served, never linked

Private-tier media is served exclusively through `GET /api/v1/private-files/{media}` (`App\Modules\Media\Http\Controllers\PrivateMediaController`), gated by `auth:sanctum` and `Gate::authorize('view', $media)` — never a raw signed URL. This means a revoked permission takes effect on the very next request, rather than remaining valid until a previously-issued signed URL expires. The route is versioned under `/api/v1` per Addendum B7; it is new code introduced in this sprint, so it complies with that decision from the start (the pre-existing, unversioned `/user` route predates B7 and is Sprint 0.1's frozen scope, out of bounds for this review).

The single most important test in this module (`tests/Feature/Media/PrivateMediaAccessTest.php`) proves this with real HTTP requests: unauthenticated → 401, authenticated → 200, non-existent media → 404 before authorization even runs. This is deliberately never inferred from "the collection is configured for the private disk" alone.

## Temporary tier is purged on a schedule, not relied upon to persist

`App\Modules\Media\Console\Commands\PurgeTemporaryMedia` (`media:purge-temporary --hours=24 --dry-run`) deletes files from the `temporary` disk older than the given threshold, scheduled daily (`routes/console.php`). It operates directly on the filesystem, not on `Media` database records — most files landing on this disk (exports, staging uploads) never go through full Media Library machinery and have no corresponding row to query.

## Extending Spatie's Media model without losing its casts

`App\Modules\Media\Models\Media extends Spatie\MediaLibrary\MediaCollections\Models\Media`, adding `HasUlids`, `LogsActivity`, `SoftDeletes`, and the `sensitivity` column. Spatie's base model declares its own casts (`uuid`, `manipulations`, `custom_properties`) via the old-style `$casts` property, not the `casts()` method — overriding `casts()` here would silently discard those. `mergeCasts()`, called from the constructor, is Eloquent's own tool for extending a parent's casts without clobbering them.

`getActivitylogOptions()` logs only `collection_name`, `disk`, `sensitivity`, `file_name`, only when actually dirty, and suppresses empty log entries (`dontLogEmptyChanges()` — note this is the installed Activitylog version's actual method name, not `dontSubmitEmptyLogs()`, which does not exist on it).

## What this sprint deliberately does not build

- No per-collection conversion profiles — those arrive with the first module that actually uploads photos (People, Phase 2).
- No OCR/AI hooks, no digital-signature tooling.
- No Document Governance parameter UI — retention/versioning configuration stays code-defined for now (Technical Debt Register).
- No fine-grained `MediaPolicy` authorization — proving the authentication boundary works is this sprint's Definition of Done, not full permission-aware access control.
