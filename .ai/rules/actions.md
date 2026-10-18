---
paths:
  - packages/*/src/Actions/**
---
# Actions

One class = one use case, single public `handle()`. No `request()`/`Inertia`/HTTP inside. Every Action has a Pest unit test in `packages/<module>/tests/Unit/Actions` that calls `handle()` directly.

Actions that take an `$actor` authorize themselves through policies and `User::outranks()` (the actor must be strictly higher than the target; exceptions: self-edit via `UserPolicy::update`, and a creator may delete another creator while more than one exists). Subject-only actions (no `$actor`: `ChangeLogin`, `UpdateAvatar`, `RemoveAvatar`, `DeleteOwnAccount`, `RegisterUser`) do not authorize — the caller must.

Create and look up users via `Support\UserModel::class()/query()` (the app's `auth.providers.users.model`), never `User::create()`/`User::query()` directly.

Invariants to preserve:
- the last creator can never be deleted (by anyone or by themselves) or demoted;
- creators cannot change roles of other creators (`outranks` is strict), so creators can't demote creators;
- system roles (`is_system`) can't be deleted and their `name`/`level` are immutable (`RolePolicy::rename`/`assignLevel`), even for creators;
- non-creators manage only roles below their level and assign only roles below their level whose permissions they hold.
