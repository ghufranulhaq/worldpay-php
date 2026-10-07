# Installation & updates

The SDK is a private Composer package called `aerotickets/worldpay-moto`. It lives in its own git repository
(`worldpay-php`), and each release is a git tag such as `v0.1.0`. Composer installs it straight from that repository,
so you don't need a package registry.

← [README](../README.md) · Next: [Configuration](configuration.md)

## 1. Install in aero-api

You need read access to the `worldpay-php` repository, through an SSH key or a GitHub token.

```bash
cd aero-api

# Tell Composer where the package lives (adds a "repositories" entry to composer.json)
composer config repositories.worldpay-moto vcs git@github.com:ghufranulhaq/worldpay-php.git

# Install the latest 0.x release
composer require aerotickets/worldpay-moto:^0.1
```

This adds the following to `composer.json`:

```json
"repositories": {
    "worldpay-moto": { "type": "vcs", "url": "git@github.com:ghufranulhaq/worldpay-php.git" }
},
"require": {
    "aerotickets/worldpay-moto": "^0.1"
}
```

Laravel discovers the service provider and the `Worldpay` facade automatically. Next:

1. Optionally publish the config file: `php artisan vendor:publish --tag=worldpay-config`
2. Add the `WORLDPAY_*` keys to `.env`. See [Laravel](laravel.md).

### CI and deploy servers

Composer has to clone the private repository in CI and on every server that runs `composer install`. Give it
access in one of two ways:

- **SSH:** add a read-only deploy key for `worldpay-php` to the server or runner.
- **HTTPS token:** `composer config --global github-oauth.github.com <token>`. If you use HTTPS, change the
  repository URL to `https://github.com/ghufranulhaq/worldpay-php.git`. In GitHub Actions, set the
  `COMPOSER_AUTH` secret to `{"github-oauth": {"github.com": "<token>"}}`.

Commit `composer.lock` in aero-api, as usual. It pins the exact SDK commit, so every environment installs the same
code.

### Docker (local dev stack)

The `api` container runs Composer inside the container, which can't see your host's SSH agent. Use the HTTPS
token form, or run `composer require` / `composer update` on the host. Then `vendor/` is shared with the container
through the `./aero-api` bind mount.

## 2. Update to a new version

1. Read the [CHANGELOG](../CHANGELOG.md) for the release. Pay attention to any **Breaking** section.
2. Update:

   ```bash
   composer update aerotickets/worldpay-moto
   ```

   This installs the newest release that `^0.1` allows. To move to a version outside the constraint, change it:

   ```bash
   composer require aerotickets/worldpay-moto:^1.0
   ```

3. If the CHANGELOG mentions new config keys, re-publish the config with
   `php artisan vendor:publish --tag=worldpay-config --force`, or add the keys by hand.
4. Run aero-api's tests and commit `composer.lock`.

### Version constraints

Releases follow [SemVer](https://semver.org).

| Constraint | Means |
|---|---|
| `^0.1` | `>=0.1.0 <0.2.0`. While the version is 0.x, a minor bump may contain breaking changes. |
| `^1.0` | `>=1.0.0 <2.0.0`. Only a major bump may break things. |
| `0.1.3` | Exactly that version |
| `dev-main` | Latest commit on `main` (not for production) |

Check what's installed with `composer show aerotickets/worldpay-moto`.

## 3. Developing the SDK and aero-api together

To try unreleased SDK changes inside aero-api, point Composer at your local checkout with a **path repository**:

```bash
cd aero-api
composer config repositories.worldpay-moto-local path ../worldpay-wrapper/worldpay-php
composer require aerotickets/worldpay-moto:@dev
```

Composer creates a symlink, so SDK edits apply immediately. The Docker `api` container only mounts `./aero-api`,
so the symlink target isn't visible inside it. Run the code on the host, or add a bind mount for
`../worldpay-wrapper/worldpay-php` to `docker-compose.yml`.

**Don't commit these changes.** Before committing aero-api, revert to the `vcs` repository and a released version.

## 4. Developing the SDK itself

```bash
cd worldpay-wrapper/worldpay-php
composer install
composer test              # unit + Laravel tests, no network
composer test:integration  # real calls to Worldpay Try (see testing.md)
composer lint              # Pint code style
```
