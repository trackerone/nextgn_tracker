# Repo Hygiene

- Legacy helper scripts (`build.sh`, `start.sh`) were removed; keep only the Docker entrypoint/CI scripts that are actively referenced to avoid confusion.
- Do not commit artifacts from `public/build`, `node_modules`, or cache directories; CI/CD should rebuild assets on demand.
- When changing stacks (PHP version, frontend tooling, tracker protocols) update the README and docs within the same commit so the instructions stay accurate.
- Delete unused helper scripts/configs only after confirming they are not referenced by Dockerfiles, CI workflows, or docs. Keep one canonical script per task.
- Never delete `app/`, `config/`, `routes/`, migrations, or referenced docs without first searching the repo and confirming alternatives exist.
