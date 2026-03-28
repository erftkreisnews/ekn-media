# AI Guardrails for this Laravel project

Do not modify or delete any of these files unless explicitly instructed:
- .env
- .env.*
- composer.json
- composer.lock
- package.json
- package-lock.json
- database/migrations/*
- config/*
- bootstrap/*
- routes/*
- app/Providers/*

Rules:
1. Never delete files without explicit approval.
2. Never rename files without explicit approval.
3. Never change environment variables.
4. Never run destructive commands.
5. Propose migration changes first, do not apply automatically.
6. For every non-trivial change, first output a plan.
7. Keep changes minimal and localized.
8. Do not refactor unrelated code.
9. Do not touch deployment, queue, or storage configuration unless explicitly requested.
10. Before editing, list the files you intend to modify.