---
paths:
  - packages/*/src/Actions/**
---
# Actions

One class = one use case, single public `handle()`. No `request()`/`Inertia`/HTTP inside. Every Action has a Pest unit test in `packages/<module>/tests/Unit/Actions` that calls `handle()` directly.
