---
paths:
  - packages/*/src/Actions/**
---
# Actions

One class = one use case, single public `handle()`. No `request()`/`Inertia`/HTTP inside. Every Action has a Pest unit test in `packages/<module>/tests/Unit/Actions` that calls `handle()` directly.

Actions that affect users authorize through policies and `User::outranks()` (the actor must be strictly higher than the target; exceptions: self-edit via `UserPolicy::update`, and a creator may delete another creator while more than one exists). Invariants to preserve: the last creator can never be deleted or demoted; system roles (`is_system`) are protected by `RolePolicy`.
