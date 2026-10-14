---
paths:
  - packages/*/src/*ServiceProvider.php
---
# Module service providers

Extend `Ladcms\Core\Module\ModuleServiceProvider`. Declare `name()`, `version()`, `coreVersion()`. Add capabilities only via `Has*` interfaces from `Ladcms\Core\Module\Contracts`. Do not load routes/migrations manually — the base class does it from `routes/`, `database/migrations`, `lang/`, `config/`.
