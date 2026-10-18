---
paths:
  - packages/*/resources/js/pages/admin/**
  - packages/*/resources/js/components/admin/**
  - packages/*/src/Http/Controllers/Admin/**
  - packages/*/src/Http/Requests/Admin/**
---
# Admin UI

- Admin pages (`<module>::admin/...`) use only the core UI kit (`@ladcms/core/components/*`, `@ladcms/core/layouts/admin-layout`); no ad-hoc design systems, no raw colors - tokens only.
- No hard-coded user-facing strings: `const t = useT()` (`@ladcms/core/lib/i18n`) and keys `<module>::admin.*` from `lang/{ru,en}/admin.php` (add both locales). Role names go through `roleName(t, name)`.
- Page title and breadcrumbs in `Page.layout` are translation keys, not literals.
- Controllers are thin: authorize and execute through Actions (Actions authorize via policies/`outranks()`), controllers only map exceptions to responses and flash a translated toast.
- Request data comes from FormRequests (validated, typed); never read raw `$request->input()` for business input. Pass typed values to Actions.
- Tables use `DataTable` with `TableQuery` on the server; settings forms come from `Settings` classes (`SettingsSchema`, `#[Field]`), not hand-written forms.
- Every admin GET route must render an existing page: keep `AdminSmokeTest` green.
