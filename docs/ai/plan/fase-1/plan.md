# Fase 1 — Modul Platform (tenancy, module registry, fondasi permission) + Identity siap-tenant

## Context

SIMAS menjadi SaaS provider–tenant (tenant = satu sekolah). Fase 0 (modularisasi) hijau: modul Shared/Identity/Core mengikuti module template, ditegakkan Deptrac 4 (`deptrac.php`) + Pest arch tests (`tests/Architecture/ModularMonolithTest.php`). Fase ini membangun `modules/Platform` (tenancy tipis + module registry + fondasi permission) lalu mengadaptasi Identity.

Keputusan terkunci (spec + jawaban user):

- Single DB + kolom `tenant_id`; ULID **hanya** untuk `tenants.id` dan semua kolom `tenant_id` (char(26), `foreignUlid`); PK lain tetap bigint.
- Resolusi tenant: subdomain `{slug}.{central_domain}` dan custom domain (kolom `domain`). Central domain = tanpa tenant, tanpa fallback; route tenant gagal. Tidak ada tenant dari session/header/query string; tidak ada override `?tenant=`.
- Pencarian tenant dibungkus interface internal `TenantResolver` di Platform (bukan Contracts); middleware hanya memanggil resolver. Satu strategi saja.
- Fail closed; Spatie teams (`team_foreign_key = tenant_id`) dibungkus kontrak Platform; hanya Platform boleh import `Spatie\`.
- Guard `provider` terpisah (`provider_users`); tanpa `Gate::before` super-admin.
- Deployment target: shared hosting/VPS + wildcard subdomain + Cloudflare; tanpa Octane/Supervisor; queue database via cron (`queue:work --stop-when-empty`); konteks tenant dibersihkan tiap akhir request.

## Fakta repo yang sudah diverifikasi

- `modules/Platform/` saat ini hanya `CONTRACT.md`. Layer Deptrac `Platform`/`PlatformPublic` **sudah ada** dengan ruleset benar (`Platform` → `platformPublic, shared, laravel, vendor`; `PlatformPublic` → `platform, shared`) — tidak perlu perubahan ruleset, cukup komentar header.
- Tidak ada package spatie terpasang → `composer require spatie/laravel-permission` (satu-satunya dependency baru, diizinkan spec).
- `composer.json`: PSR-4 per modul (App/Database/Tests) → tambahkan tiga entri `Modules\Platform\*` (pola sama dengan Identity/Core, termasuk duplikat Tests di autoload-dev).
- `tests/Pest.php` otomatis meregistrasi semua `modules/*/tests/{Feature,Unit}` — tidak perlu diubah.
- Arch test: `$modules = ['Identity','Core']` → tambah `Platform`; daftar direktori FK-scan → tambah `modules/Platform/database/migrations` (atau loop `modules/*/database/migrations`). Whitelist `tenant` untuk FK sudah ada.
- `bootstrap/providers.php` → daftarkan `PlatformServiceProvider` sebelum Identity.
- Identity: migration `0001_01_01_000000_create_users_table.php` (users/password_reset_tokens/sessions), User model + factory, **belum ada controller auth** (routes masih placeholder) — scaffold auth tenant-scoped dibangun di stage 9.
- `phpunit.xml`/Pest 5, `php artisan test`, `composer test` = lint:check + phpstan(7) + deptrac + tests; CI Linux (`composer ci:check`). Frontend check = `npm run check` (vp check, bukan ESLint).
- Windows dev + CI Linux: hati-hati casing file (git index vs disk).

## Stages (satu commit logis per stage, semua gate hijau sebelum lanjut)

Gate tiap stage: `composer dump-autoload` (bila composer.json berubah), `php artisan test --compact`, `vendor/bin/deptrac analyse`, `composer types:check`, `vendor/bin/pint --dirty --format agent`, `npm run check` bila frontend tersentuh, `php artisan migrate:fresh` di stage yang menambah/mengubah migration.

### Stage 1 — Deptrac + arch tests (sebelum kode Platform)

1. `deptrac.php`: hanya pembaruan komentar (layer Platform sudah ada). Tidak ada pelonggaran.
2. `tests/Architecture/ModularMonolithTest.php`: tambah `Platform` ke `$modules` (test "no module imports internal of another module" + User-import ban yang sudah menyebut `modules/Platform`); tambah direktori migrasi Platform ke FK-scan; tambah test "no `Spatie\Permission` outside Platform" sudah ada (dir list sudah termasuk `modules/Platform` sebagai satu-satunya yang diperbolehkan karena tidak discan).
3. Bukti enforcement: buat pelanggaran sementara (file Core meng-import internal Platform) → `composer deptrac` gagal → hapus.
4. Commit: "chore(arch): wire Platform layer enforcement".

### Stage 2 — Tenant model, TenantContext, resolusi tenant

File baru di `modules/Platform/`:

- `database/migrations/0000_01_01_000000_create_tenants_table.php` — `id` ULID (`HasUlids`), `name`, `slug` unique (regex kecil/angka/dash; reserved slugs dari `config/tenancy.php`: www, admin, api, app, central, …), `domain` nullable unique, `timezone` default `Asia/Jakarta` (divalidasi `timezone_identifiers_list()`), `status` (active/suspended, default active), `settings` json nullable, timestamps + softDeletes. **Urutan**: nama `0000_…_create_tenants_table` memastikan berjalan sebelum `users` (FK `tenant_id → tenants`); buktikan dengan `migrate:fresh`.
- `app/Domain/Models/Tenant.php` (+ `TenantFactory`): `UseFactory`, cast `settings: array`, enum-ish status; dispatch `Contracts\Events\TenantCreated(tenantId)` pada event `created`.
- `app/Contracts/TenantData.php` — readonly DTO: `id, name, slug, timezone, status`.
- `app/Contracts/TenantContext.php` (interface) + `app/Infrastructure/Tenancy/DefaultTenantContext.php` (singleton): `current(): ?TenantData`, `currentOrFail(): TenantData`, `id(): ?string`, `timezone(): string`, `set(string $tenantId)`, `forget()`, `run(string $tenantId, callable $cb): mixed` (restore konteks sebelumnya di `finally`, aman bersarang), `runWithoutTenant(callable $cb): mixed`. `set()`/`forget()` juga set/forget Spatie `setPermissionsTeamId` + reset cache permission (diimplementasi penuh di stage 6; panggilan dijembatani via wrapper internal agar stage 2 tidak butuh Spatie).
- `config/tenancy.php` — `central_domains` (env, default `localhost`), `reserved_slugs`, `default_timezone`.
- `app/Infrastructure/Tenancy/TenantResolver` (interface internal) + `SubdomainTenantResolver`: **normalisasi host** (lowercase, strip port), host ∈ central_domains → null; `.{central}` suffix → lookup by `slug` (**hanya satu tingkat subdomain** — `a.b.localhost` tidak dianggap slug); lainnya → lookup by `domain`. null → 404 generik; suspended → 403. Konfigurasi trusted hosts/proxies untuk Cloudflare didokumentasikan di docs (stage 10).
- `app/Http/Middleware/ResolveTenant.php` (internal): `forget()` di awal request; panggil resolver, `set()`, dan `forget()` lagi di akhir request (terminator) — Octane-safe. **Priority middleware** (bootstrap/app.php): `ResolveTenant` berjalan sebelum `SubstituteBindings` dan sebelum autentikasi, agar route model binding dan auth tunduk pada scope tenant; test membuktikan binding + auth pada route tenant.
- Konteks central tidak boleh melempar `TenantNotSetException`: shared-props middleware dan halaman central tidak memanggil kode yang memaksa konteks tenant (mis. tidak memanggil `Auth::user()` guard web di central).
- `app/Infrastructure/Providers/PlatformServiceProvider.php` (registrasi migration, singleton bindings, middleware alias nanti) + `bootstrap/providers.php` update.
- `composer.json`: tambah PSR-4 Platform.
- `CONTRACT.md` Platform mulai diisi aktual.
- Tests: resolver test (unit, terpisah dari middleware), middleware test (subdomain valid/suspended/unknown/central), konteks tidak bocor antar request berurutan, slug reserved + timezone invalid ditolak, SESSION_DOMAIN/cookie per host (session.domain null → host-only cookie; test atribut Set-Cookie).
- Commit.

### Stage 3 — BelongsToTenant + TenantScope + macro migrasi

- `app/Contracts/Concerns/BelongsToTenant.php` (public trait) → delegasi ke `app/Infrastructure/Tenancy/TenantScope.php` (internal global scope):
    - Query tanpa tenant aktif → throw `app/Contracts/Exceptions/TenantNotSetException.php`, kecuali di dalam `runWithoutTenant()`.
    - `creating` mengisi `tenant_id` dari konteks; `updating` mencegah perubahan `tenant_id` pada model tersimpan.
    - **Tanpa relasi `tenant()`** — model Tenant internal tidak bocor ke publik (keputusan user; relasi internal, jika perlu, didefinisikan di model internal saja).
    - Eksplisit bypass: `Model::withoutTenancy()` (metode trait mengembalikan `query()->withoutGlobalScope(TenantScope::class)`) — penggunaan mencolok.
    - Bulk `update()/delete()` Eloquent tunduk scope (test); dokumentasikan `DB::table()` tidak terlindungi.
- Macro `Blueprint::tenantId()` (`foreignUlid('tenant_id')->constrained('tenants')` + index) — registrasi via provider.
- Tests: isolasi A vs B (get/first/count/relasi/route model binding), create auto-fill, exception tanpa konteks, `runWithoutTenant` normal, tenant_id immutable, bulk ops, unique komposit (sama di dua tenant OK, dobel di tenant sama gagal) — pakai model test-only `test_things` (tabel dibuat via `Schema::create` di `beforeEach` suite Platform, bukan migration produksi).
- Commit.

### Stage 4 — Queue, cache, storage tenant-aware

- Queue: `Queue::createPayloadUsing` menempel `tenant_id` dari konteks ke payload; **JobProcessing: simpan konteks sebelumnya lalu `set()`**; pada `JobProcessed`/`JobFailed`/`JobExceptionOccurred` **pulihkan konteks sebelumnya (null bila tidak ada) — jangan `forget()` buta**. Alasan: driver `sync` menjalankan job di dalam request dan tidak boleh menghapus konteks pemanggil. Berlaku otomatis untuk queued listener/mail/notification. Job tanpa tenant jalan tanpa konteks.
- `app/Contracts/TenantCache.php` + implementasi: prefix `tenant:{id}:` (central: `central:`); API `get/put/forget/remember` + `key(string $key): string`.
- `app/Contracts/TenantStorage.php` + implementasi: `path(string $module, string $relative): string` → `tenants/{tenant_id}/{module}/{relative}` di disk `private` (default); wrapper read/write/delete yang selalu memakai path tenant aktif.
- Tests: job di tenant A jalan dengan konteks A (queued listener + job), **dispatch sync di tenant A → konteks tetap A sesudahnya**, dua job berurutan pada worker sama tidak saling mewarisi konteks, cache key sama di A/B tidak tabrakan, path selalu di bawah `tenants/{id}/`, permission cache tidak bocor antar tenant (lanjut stage 6).
- Commit.

### Stage 5 — Module registry + feature flag

- `database/migrations/…_create_tenant_modules_table.php`: ULID id, `tenant_id` FK, `module`, `enabled`, `enabled_at`, `expires_at` nullable, `meta` json nullable, timestamps, unique(`tenant_id`,`module`).
- `app/Contracts/ModuleRegistry.php`: `register(string $key, string $label)`, `all()`, `exists()` — tiap modul mendaftar dari provider miliknya (Core mendaftar `core`, Identity `identity`); Platform tidak hardcode modul bisnis; key tak terdaftar ditolak saat enable.
- `app/Contracts/TenantModules.php`: `isEnabled(string $module, ?string $tenantId = null): bool` (hormati `enabled` + `expires_at`), hasil di-cache via TenantCache, invalidate saat berubah. Servis enable/disable internal untuk command.
- Middleware alias `module:{key}` (registrasi alias di provider): modul tidak aktif → **403** (keputusan tercatat; bukan 404); modul `core` selalu aktif; key tak terdaftar → 403.
- Tests: module:x menolak/meloloskan, `expires_at` lewat = nonaktif, key tak terdaftar ditolak, perubahan flag langsung tercermin (cache invalidate).
- Commit.

### Stage 6 — Permission (Spatie teams) + ProviderUser

- `composer require spatie/laravel-permission`; publish config `config/permission.php`: `teams = true`, `team_foreign_key = 'tenant_id'`; **modifikasi migration bawaan**: `roles.tenant_id` **tetap nullable** (sesuai desain teams Spatie — global role dimungkinkan); pivot `model_has_roles`/`model_has_permissions` memakai `tenant_id` sebagai bagian primary key (**wajib terisi**, `foreignUlid('tenant_id')` constrained ke `tenants` — FK exception legal); PK pivot tetap `id` bigint; migration milik Platform (dimuat setelah tenants). Keputusan nullable-vs-required ini dicatat di CONTRACT.md Platform.
- `app/Contracts/PermissionRegistry.php`: `register(string $module, array $permissions)` — dipanggil dari provider tiap modul.
- `app/Contracts/Concerns/HasTenantRoles.php` (public trait, wrapper `Spatie\HasRoles`) — dipakai Identity di stage 9.
- Provider auth minimal: `provider_users` table (id bigint, name, email unique, password, timestamps), model `ProviderUser`, guard `provider` di `config/auth.php`, factory, command `provider:create-user` (stage 7). Tanpa UI, tanpa `Gate::before`.
- Wrapper internal Spatie (forget permission cache saat ganti team, dsb.) — semua di Platform.
- Tests: role/permission tenant A tidak berlaku di B (role bernama sama dua tenant), pindah tenant dalam satu proses benar, permission benar di queue job, guard provider tidak bisa route tenant (dan sebaliknya) — user test-only di suite Platform.
- Commit.

### Stage 7 — Artisan commands + dev seeder

- `tenant:create {name} {slug} {--domain=} {--timezone=Asia/Jakarta} {--modules=*}` (validasi slug unik non-reserved, timezone valid, modul terdaftar; dispatch TenantCreated).
- `tenant:list`, `tenant:modules {tenant} {--enable=*} {--disable=*}`, `tenant:suspend`, `tenant:activate`, `tenant:run {tenant} {artisan-command}` (dalam `TenantContext::run()`), `provider:create-user`, `permissions:sync` (idempotent, tidak menghapus assignment).
- Dev seeder: sekolah-a (Asia/Jakarta) + sekolah-b (Asia/Makassar/WITA), modul berbeda — dipanggil dari `DatabaseSeeder` saat `app()->isLocal()`.
- Tests: `permissions:sync` idempotent; validasi command; `tenant:run` menyetel konteks.
- Commit.

### Stage 8 — Frontend share (minimal)

- Middleware Inertia `ShareTenantContext` (Platform): shared props hanya `tenant` (name, slug, timezone) dan `modules` (key aktif); central → `tenant: null`. Terpasang setelah `ResolveTenant` di grup web.
- Hook di `modules/Shared/resources/js/hooks/`: `useTenant()` dan `hasModule(key)` — hanya membaca shared props, tidak mengenal internal Platform.
- Verifikasi `npm run check` + `npm run types:check`.
- Commit.

### Stage 9 — Identity siap-tenant (commit terpisah)

- `users`: tambah `tenantId()`, `unique('tenant_id','email')` menggantikan unique email tunggal (migration diubah langsung — belum ada data).
- `User` model: `BelongsToTenant` + `HasTenantRoles` dari PlatformPublic; **tidak** import Spatie.
- Auth **hanya login/logout** (session-based, tenant-routed di grup `ResolveTenant`); **tanpa register, tanpa lupa password — Fase 2**. Rate limiter login memakai key yang menyertakan `tenant_id`.
- **`password_reset_tokens` tidak disentuh sekarang; TODO Fase 2** (catat di docs/CONTRACT.md).
- Factory/seeders/tests Identity jalan dalam konteks tenant (`TenantContext::run()` atau factory state).
- `CONTRACT.md` Identity: Allowed dependencies = Shared + PlatformPublic.
- Tests: email sama di dua tenant OK; login di A tidak bisa masuk di B; **cookie sesi dari tenant A tidak mengautentikasi di host tenant B**; login/logout bekerja dalam konteks tenant dan gagal tanpa konteks.
- Commit.

### Stage 10 — Dokumentasi + ringkasan

- `docs/architecture/modular-monolith.md`: aturan tenancy (cara membuat model tenant baru, aturan migrasi & unique komposit, job/cache/storage tenant-aware, cara modul mendaftarkan module key & permission, jebakan: `DB::table`, `withoutTenancy`, Octane, casing, dev via `*.localhost`, syarat produksi: wildcard DNS/SSL, SESSION_DOMAIN per subdomain, queue cron).
- `AGENTS.md` (tracked) + `CONTRACT.md` Platform final (tabel: tenants, tenant_modules, provider_users, tabel Spatie; publik surface; event TenantCreated; deps hanya Shared).
- Ringkasan akhir: asumsi, keputusan menyimpang + alasan, risiko tersisa, usulan Fase 2 (role default, seeding role via TenantCreated, pengelolaan user).
- Commit.

## Peta test → kriteria spec §6

File test di `modules/Platform/tests/{Feature,Unit}` (+ adaptasi `modules/Identity/tests`):

- `TenantIsolationTest` → item 1–5 (query/find/count/relasi/binding, auto-fill, exception, runWithoutTenant, immutable, bulk, unique komposit)
- `TenantResolutionTest` (middleware) + `TenantResolverTest` (unit, terpisah) → item 6–9
- `SessionIsolationTest` → item 10
- `TenantQueueTest` → item 11–12
- `TenantCacheStorageTest` → item 13–14
- `ModuleFlagTest` → item 15–16
- `PermissionTenancyTest` → item 17–21
- `IdentityTenancyTest` (di Identity) → item 22–23
- Arch tests + deptrac → item 24–25

Test-only models (`test_things`, user permission) hanya di suite Platform; tabel dibuat via `Schema::create` di setup, bukan migration produksi.

## Risiko & default yang diambil

- `laravel/pao` di composer.json: diperlakukan sebagai dep dev yang ada; tidak disentuh.
- **Koreksi user (disetujui)**: queue pulihkan konteks sebelumnya (bukan forget); ResolveTenant priority sebelum bindings/auth; central tidak melempar TenantNotSetException; Stage 9 hanya login/logout (register & lupa password = Fase 2, password_reset_tokens TODO); relasi `tenant()` dihapus dari trait publik; `roles.tenant_id` nullable + pivot tenant_id wajib (bagian PK); resolver normalisasi host + satu tingkat subdomain + docs trusted proxies Cloudflare.
- Urutan migration via timestamp `0000_…` + urutan provider (Platform sebelum Identity); dibuktikan `migrate:fresh`.
- Tidak ada dependency baru selain `spatie/laravel-permission`.
