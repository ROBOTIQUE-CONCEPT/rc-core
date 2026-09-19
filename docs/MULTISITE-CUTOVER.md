# RC www / my — Cutover Leads & permissions

> **Historical note (2026-09-19):** this runbook was written for RC Core
> `0.6.0-alpha2` and a "RC Leads" installed as its own separate plugin —
> both long superseded (Core is at `0.6.0-alpha11`; `my`-side business
> logic, including leads, is now the `leads` module embedded in a single
> `RC Portal` plugin — see `docs/ARCHITECTURE.md`'s Amendments section).
> Step 2 below cannot be followed as written today. Kept for historical
> reference of how a prior cutover was planned, not as a live runbook —
> re-derive the actual coordinated-update order from the current
> `rc-core`/`rc-portal`/`rc-portal-theme` versions before attempting a
> real cutover.

This runbook assumes WordPress Multisite already exists and both `www` and `my` respond.

## 1. Maintenance / backup

Enable a short maintenance window and export the database before replacing plugin files. Plugin files are shared by all subsites.

## 2. Update coordinated releases

Install in this order while maintenance is active:

1. RC Core `0.6.0-alpha2` (network active)
2. RC Catalog `1.7.0-alpha2`
3. RC Leads `0.3.0-alpha2`

Keep RC Leads activated on `www` until the data cutover is validated; alpha2 will refuse to boot business runtime there.

## 3. Configure site roles

Network Admin > RC Core > Settings:

- Public site = `www`
- Application site = `my`
- Connector site = the site currently holding connector credentials

## 4. Activate Leads on my

Ensure RC Leads is active on `my`. Determine IDs with:

```bash
wp site list --fields=blog_id,url --path=/path/to/wordpress
```

## 5. Migrate Leads

```bash
wp rc leads migrate-site --from=<www-id> --to=<my-id> --dry-run --url=https://my.robotiqueconcept.com --path=/path/to/wordpress
wp rc leads migrate-site --from=<www-id> --to=<my-id> --force --url=https://my.robotiqueconcept.com --path=/path/to/wordpress
```

`--force` is expected when the target installer has seeded default forms. The source tables are never deleted.

## 6. Synchronize public form projections

```bash
wp rc catalog sync-lead-forms --url=https://www.robotiqueconcept.com --path=/path/to/wordpress
```

## 7. Validate a real submission

Submit contact, maintenance and product forms from `www`; verify the new opportunities exist on `my` and not in the old `www` tables.

## 8. Disable Leads on www

After successful validation:

```bash
wp plugin deactivate rc-leads --url=https://www.robotiqueconcept.com --path=/path/to/wordpress
```

Leave Leads active on `my`.

## 9. Permissions

Network Admin > Settings > Rôles & permissions RC:

- configure the capability matrix;
- assign network users to application-site profiles;
- verify customer/partner access separately from internal profiles.

## 10. Turnstile Woo

Network Admin > Turnstile:

- enable WooCommerce login protection;
- enable WooCommerce registration protection if registration is enabled.

Validate `/my-account/` login before leaving maintenance.

## Rollback

If cutover fails:

- restore previous plugin packages;
- keep/restore Leads active on `www`;
- source Leads tables remain untouched;
- restore DB snapshot only if writes occurred that must be reverted.
