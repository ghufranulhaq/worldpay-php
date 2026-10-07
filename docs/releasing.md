# Releasing

How to publish a new version of `aerotickets/worldpay-moto`. This is for SDK maintainers.

← [API reference](api-reference.md) · [Installation & updates](installation.md) (consumer side)

## Versioning

[SemVer](https://semver.org): `MAJOR.MINOR.PATCH`.

| Change | Bump |
|---|---|
| Bug fix, docs, internal refactor | PATCH (`0.1.0` → `0.1.1`) |
| New method, option or instrument; backwards compatible | MINOR (`0.1.x` → `0.2.0` while 0.x, `1.x` → `1.y` after 1.0) |
| Removed/renamed public API, changed behaviour, new required config | While 0.x: MINOR. After 1.0: MAJOR. |

The public API is everything in [API reference](api-reference.md). Classes marked `@internal` can change in any
release.

## Steps

1. Make sure `main` is green:

   ```bash
   composer test && composer lint
   composer test:integration     # with Try credentials in .env.testing
   ```

2. Update `src/Version.php`, which is sent in the User-Agent: `public const SDK = '0.2.0';`
3. Update `CHANGELOG.md`: move "Unreleased" items under the new version and date. List anything breaking under
   **Breaking**, with migration steps.
4. Commit and tag:

   ```bash
   git commit -am "Release v0.2.0"
   git tag -a v0.2.0 -m "v0.2.0"
   git push origin main --tags
   ```

5. Tell the aero-api team. They run `composer update aerotickets/worldpay-moto`, or change the constraint for a
   breaking release. See [Installation & updates](installation.md#2-update-to-a-new-version).

If the change affects the browser flow (e.g. how sessions are used), release a matching
`@aerotickets/worldpay-checkout` version and note the compatible versions in both CHANGELOGs.

## Changing the Worldpay API version

The SDK targets `WP-Api-Version: 2024-06-01`. To move to a newer version:

1. Read Worldpay's changelog for the new version.
2. Update the request and response classes.
3. Change `Config::DEFAULT_API_VERSION`.
4. Run the integration suite against Try.
5. Release a new MINOR (0.x) or MAJOR (1.x+) version.
