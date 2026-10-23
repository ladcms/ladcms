---
paths:
  - packages/*/resources/js/pages/**
  - packages/*/resources/js/components/public/**
  - packages/*/resources/js/components/profile/**
  - packages/*/resources/js/widgets/**
  - packages/*/resources/js/profile/**
  - packages/*/resources/js/header-actions/**
  - packages/*/resources/js/layouts/public-layout.tsx
  - packages/*/src/Http/Controllers/*.php
  - packages/*/src/Http/Resources/**
  - packages/*/src/Ui/**
---
# Public UI

- Public pages are `<module>::Path/Page` and render in `PublicLayout`; widgets, profile sections/actions and header actions are `<module>::<Name>` -> `resources/js/<widgets|profile|header-actions>/<Name>.tsx` (flat). Import core UI only via `@ladcms/core/*`.
- No hard-coded user-facing strings: `useT()` and `<module>::public.*` keys from `lang/{ru,en}/public.php` (both locales). Placeholders `:name`; role names via `core::public.roles.*` / `publicRoleName`.
- Every page sets meta with `<Seo>` (never a bare `<Head>`); links use Wayfinder route helpers, not literal paths.
- Accessibility is part of done: labelled `nav` landmarks, `aria-current` on active links, `aria-label` on icon-only controls, `focus-visible:ring-2 focus-visible:ring-ring` on interactive elements, `role=tablist/tab/tabpanel` + arrow keys for tabs, colour contrast >= 4.5:1 (use tokens).
- Other users' data goes through `PublicUserResource` / `ProfileResource` (privacy: `show_email`, `show_birthday`, owner sees own); never pass a `User` model or raw email/birth date to props. `auth.user.roles` is `[{name, color}]`.
- Forms that change public content check `canWrite()` on the server (403) and disable controls when `auth.user.can_write` is false.
- Never put `{data: [...]}` resources into props unresolved: call `->resolve($request)`. Keep `PublicSmokeTest` green; every Action has a unit test without HTTP.
- Error pages are `core::error` via `ErrorPageRenderer`; do not hand-roll error views.
