# devkit-env

PHP CLI for **named `.env` profiles** (save, switch, backup) and **cross-environment drift reports** (diff). One binary: **`./vendor/bin/devkit-env`**.

Composer package: **`devkit/env`**. Composer also exposes the same entrypoint as **`./vendor/bin/devkit-env-diff`** (alias).

## What it’s for

- **Diff** two or more `.env` files and see missing keys, extra keys, and value mismatches (with optional masking); use **`--format side-by-side`** for a two-column view.
- **Merge** two `.env` files interactively (pick left/right on conflicts, include/skip keys that exist on only one side) or with **`--prefer`** in scripts; **`--dry-run`** previews the result (with **`--out`**, does not write the file).
- **Manage the active env file** (usually `.env`): copy a saved profile over it, with **automatic backup** of what was there before.
- **Create saved profiles** from **`./.env`** when you omit **`--from`**, or from **any file** with **`--from`** (e.g. `./vendor/bin/devkit-env save --from .env.staging`). This is separate from **`defaultEnv`** / **`targetEnv`**, which only affect **`use`** (see [Save a profile](#save-a-profile)).
- **Switch** the current project to a saved profile (`./vendor/bin/devkit-env use <name>`).
- **List** and **delete** saved profiles (`./vendor/bin/devkit-env list`, `./vendor/bin/devkit-env delete` / `rm`) without touching your working `.env` unless you `use`.
- **`.devkit-env.json`** can run **shell commands after a switch** (`afterSwitch`, `afterSwitchProfiles`) — e.g. `php artisan config:clear`.

## Prerequisites

- PHP **8.3+**
- Composer

## Install

```bash
composer require devkit/env
# or clone this repo and run:
composer install
```

## Run the CLI (Composer / `vendor`)

After `composer require` or `composer install`, run the tool **from your application’s project root** (where `.env` and `composer.json` live) so paths and `.devkit-env.json` resolve correctly.

**Recommended:** call the binary Composer exposes under `vendor/bin`:

```bash
./vendor/bin/devkit-env --help
./vendor/bin/devkit-env save staging
# copies ./.env into the store as profile "staging" (use --from PATH for another file)

./vendor/bin/devkit-env save staging --from .env.staging
```

The same executable is available as `./vendor/bin/devkit-env-diff` (alias).

**Alternatives:**

- `composer exec devkit-env -- --help` — runs the bin without typing `vendor/bin` (needs Composer on your `PATH`).
- **Windows:** use `vendor\bin\devkit-env.bat`, or `php vendor/bin/devkit-env` from the project root.

## Commands overview

| Command | Purpose |
|--------|---------|
| `./vendor/bin/devkit-env save` | Copy into the store under a **profile name** (`env/` + `registry.json`). **Default source:** **`./.env`** if **`--from`** is omitted (ignores **`defaultEnv`** in config). |
| `./vendor/bin/devkit-env use` | Copy a saved profile **onto** the path from **`defaultEnv`** / **`targetEnv`** (usually `.env`), **backing up** the previous file first. |
| `./vendor/bin/devkit-env list` | List saved profile names. |
| `./vendor/bin/devkit-env delete` / `./vendor/bin/devkit-env rm` | Remove a saved profile (registry entry + file under `env/`). |
| `./vendor/bin/devkit-env diff` | Compare multiple `.env` files (baseline vs targets) — missing keys, extras, value drift, optional masking. Use `--format side-by-side` (alias `wide`) for two columns. |
| `./vendor/bin/devkit-env merge` | Merge **two** `.env` files: interactive prompts for conflicts and keys on one side only; use `-n` and `--prefer left` or `right` when stdin is not a TTY. **`--dry-run`** previews output; with **`--out`**, prints what would be written without creating the file. |

Run `./vendor/bin/devkit-env --help` for a short summary. For diff-only flags: `./vendor/bin/devkit-env diff --help`. For merge: `./vendor/bin/devkit-env merge --help`.

**Legacy invocation:** you can still run `./vendor/bin/devkit-env-diff …` with the same entrypoint; arguments that start with `-` are treated as **diff** mode (same as before).

## Layout (created in your project root)

Default paths (override with `.devkit-env.json`):

- **`env/`** — stored profile files (e.g. `staging.env`) and **`env/registry.json`** (maps profile names → filenames).
- **`env/backups/`** — timestamped backups of the file being replaced when you `use` a profile.

On first `save`, `use`, `list`, or `delete`, the tool appends a marked block to **`.gitignore`** (or creates the file) so typical patterns are ignored:

- `/env/` (store + backups when using defaults)
- `/env/backups/` only when backup dir is configured outside `env/`

You may commit **`.devkit-env.json`** (paths only); keep **`env/`** and backups out of git.

### Optional config: `.devkit-env.json`

**`defaultEnv`** / **`targetEnv`** control where **`use`** writes. They do **not** change which file **`save`** reads when **`--from`** is omitted — that is always **`./.env`** in the project root.

```json
{
  "storeDir": "env",
  "backupDir": "env/backups",
  "defaultEnv": ".env",
  "afterSwitch": [
    "php artisan config:clear",
    "php artisan cache:clear"
  ],
  "afterSwitchProfiles": {
    "production": [
      "php artisan migrate --force --no-interaction"
    ]
  }
}
```

- **`defaultEnv`** — path **`use`** applies a profile to (your “active” env file). Often `.env`. Relative to the project directory unless absolute.
- **`targetEnv`** — same as **`defaultEnv`** for **`use`**. If both are set, **`targetEnv` wins** (alias for older configs).

**Overrides for a single run:**

- **`use`:** `./vendor/bin/devkit-env use staging --target other/path/.env`
- **`save`:** `./vendor/bin/devkit-env save --name x --from other/path/.env` — copy **from** that file **into** the store. Omit **`--from`** to copy **from `./.env`** only.

- **`afterSwitch`** — shell commands run in order after **every** successful `./vendor/bin/devkit-env use` (from the project directory).
- **`afterSwitchProfiles`** — optional extra commands for specific profile **names** (same spelling as when you `save` / `use`). Those run **after** the global `afterSwitch` list.

Use **`./vendor/bin/devkit-env use --skip-hooks`** to apply a profile without running these commands (e.g. in CI).

All paths are relative to the current working directory unless absolute.

## Save a profile

**What gets copied:** If you **do not** pass **`--from`**, **`save`** reads **`./.env`** (project root). It does **not** use **`defaultEnv`** / **`targetEnv`** from `.devkit-env.json` — those keys only affect **`use`**. To snapshot another file, pass **`--from PATH`**.

| Situation | Command |
|-----------|---------|
| Save the current **`.env`** as profile `staging` | `./vendor/bin/devkit-env save staging` or `./vendor/bin/devkit-env save --name staging` |
| Save a **different** file | `./vendor/bin/devkit-env save staging --from .env.staging` |

Non-interactive (profile name via `--name` or a **positional** first argument):

```bash
./vendor/bin/devkit-env save staging
./vendor/bin/devkit-env save staging --from .env.staging
# same as:
./vendor/bin/devkit-env save --name staging --from .env.staging
```

Interactive (TTY): run `./vendor/bin/devkit-env save` — pick an existing profile by **number** to overwrite, or type a **new name**.

- `--force` — overwrite when the profile name already exists.

## Switch active `.env` (use)

```bash
./vendor/bin/devkit-env use staging
```

- **`--target PATH`** — file to overwrite (default: `targetEnv` from config, usually `.env`).
- **`--backup-dir PATH`** — where backups go (default: `backupDir` from config).
- **`--no-backup`** — do not copy the current target before replacing.

Interactive (TTY): `./vendor/bin/devkit-env use` without a name shows a numbered list.

## List profiles

```bash
./vendor/bin/devkit-env list
```

## Delete a saved profile

Removes the name from `env/registry.json` and deletes the corresponding file under `env/`. Does **not** change your current `.env` unless you run `use`.

```bash
./vendor/bin/devkit-env delete staging
# or
./vendor/bin/devkit-env rm staging
```

- **`--force`** — skip the “are you sure?” prompt in a TTY.
- Interactive (TTY): `delete` / `rm` with no name shows a numbered list; you still confirm unless `--force`.

## Diff (drift between env files)

Explicit subcommand:

```bash
./vendor/bin/devkit-env diff --baseline=local \
  --env local=examples/env/local.env \
  --env production=examples/env/production.env
```

Or legacy (no `diff` keyword), same as earlier releases:

```bash
./vendor/bin/devkit-env --baseline=local --env local=.env --env prod=.env.prod
```

Options: `--format=text|json`, `--no-mask`, `--mask-key PATTERN` (repeatable).  
Exit codes: **0** no drift, **1** drift, **2** error.

## Library API

Load Composer’s autoloader, then use the namespaces (most people only need the CLI binary above):

```php
require __DIR__ . '/vendor/autoload.php';

// e.g. Devkit\Env\Diff\EnvFileParser, Devkit\Env\Store\ProjectConfig::load(), …
```

Namespace **`Devkit\Env`**:

- **`Devkit\Env\Diff\`** — parser, comparer, masking, formatters.
- **`Devkit\Env\Store\`** — `ProjectConfig`, `EnvProfileManager` (save / apply / delete / list), `ProfileRegistry`, `GitignoreManager`, `PostSwitchCommandRunner`.

## Development

```bash
composer run tests
composer run standards:check
```

## License

MIT
