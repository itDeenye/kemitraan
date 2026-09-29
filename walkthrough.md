# Walkthrough: Setup Vue 3 + Vuetify di Laravel 13

## Ringkasan

Setup Vue 3 dengan arsitektur **multi-panel** (2 entry point terpisah) di proyek Laravel 13:

- **Member** (`/member`) — Dashboard member setelah login
- **Admin** (`/admin`) — Panel admin untuk pengelolaan sistem

Setiap panel memiliki Vue app instance, router, dan layout sendiri, tapi berbagi komponen, composables, services, dan types melalui folder `shared/`.

## Tech Stack yang Terinstall

| Package               | Versi  | Fungsi                        |
| --------------------- | ------ | ----------------------------- |
| `vue`                 | ^3.5   | Framework UI                  |
| `vue-router`          | ^4.5   | Client-side routing           |
| `pinia`               | ^3     | State management              |
| `axios`               | ^1.9   | HTTP client                   |
| `vuetify`             | ^3.8   | UI Component Library          |
| `@mdi/font`           | ^7     | Material Design Icons         |
| `@vitejs/plugin-vue`  | latest | Vite Vue plugin               |
| `vite-plugin-vuetify` | latest | Vuetify auto-import           |
| `typescript`          | ^5.8   | Type checking (non-vue files) |
| `vue-tsc`             | latest | Vue TS compiler               |
| `sass-embedded`       | latest | SASS/SCSS support (Vuetify)   |

## Struktur Folder Frontend

```
resources/js/
├── env.d.ts                           # Vue type shims
├── shared/                            # ── Kode bersama ──
│   ├── plugins/
│   │   └── vuetify.ts                 # Konfigurasi Vuetify (tema, defaults)
│   ├── services/
│   │   └── api.ts                     # Axios instance + interceptors
│   ├── types/
│   │   └── index.ts                   # Shared interfaces (User, ApiResponse, dll)
│   ├── components/.gitkeep
│   ├── composables/.gitkeep
│   ├── stores/.gitkeep
│   └── utils/.gitkeep
│
├── member/                            # ── Panel MEMBER ──
│   ├── app.ts                         # Entry point
│   ├── App.vue                        # Root component
│   ├── router/index.ts                # Routes (base: /member)
│   ├── layouts/DashboardLayout.vue    # Sidebar + topbar
│   ├── pages/Dashboard.vue            # Dashboard page
│   ├── components/.gitkeep
│   └── composables/.gitkeep
│
└── admin/                             # ── Panel ADMIN ──
    ├── app.ts                         # Entry point
    ├── App.vue                        # Root component
    ├── router/index.ts                # Routes (base: /admin)
    ├── layouts/AdminLayout.vue        # Sidebar + topbar + theme toggle
    ├── pages/Dashboard.vue            # Dashboard page
    ├── components/.gitkeep
    └── composables/.gitkeep
```

## File Konfigurasi yang Diubah/Dibuat

| File                                                                   | Aksi      | Keterangan                                   |
| ---------------------------------------------------------------------- | --------- | -------------------------------------------- |
| [package.json](file:///c:/laragon/www/esoftdream/api/package.json)     | Modified  | Tambah dependencies Vue ecosystem            |
| [vite.config.js](file:///c:/laragon/www/esoftdream/api/vite.config.js) | Rewritten | 3 entry point, Vue + Vuetify plugin, @ alias |
| [tsconfig.json](file:///c:/laragon/www/esoftdream/api/tsconfig.json)   | New       | TypeScript config dengan path alias          |
| [web.php](file:///c:/laragon/www/esoftdream/api/routes/web.php)        | Rewritten | SPA catch-all routes per panel               |

## Blade Templates

| File                                                                                       | Route            | Panel  |
| ------------------------------------------------------------------------------------------ | ---------------- | ------ |
| [member.blade.php](file:///c:/laragon/www/esoftdream/api/resources/views/member.blade.php) | `/member/{any?}` | Member |
| [admin.blade.php](file:///c:/laragon/www/esoftdream/api/resources/views/admin.blade.php)   | `/admin/{any?}`  | Admin  |

## Cara Menjalankan

```bash
# Development (hot reload)
npm run dev

# Build production
npm run build

# Laravel server (perlu composer install dulu)
php artisan serve
```

> [!NOTE]
> Untuk development, jalankan `npm run dev` dan `php artisan serve` secara bersamaan. Atau gunakan `composer run dev` jika sudah menjalankan `composer install`.

## Verifikasi

- ✅ `npm run build` — 252 modules, 8.22 detik, 0 error
- ⏸ Laravel routes — belum bisa diverifikasi (vendor belum ter-install, tapi route syntax valid)
