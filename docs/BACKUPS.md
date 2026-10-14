# PostgreSQL backups

## Daily dump

```bash
pg_dump -Fc -d travora -f /var/backups/travora/travora-$(date +%F).dump
```

Keep dumps on a private volume or object storage with encryption. Never serve this directory over HTTP. Dumps contain encrypted credential references; treat them as secret.

## Retention

- Daily: 14 days
- Weekly: 8 weeks
- Monthly: 12 months

## Restore

```bash
php artisan down
pg_restore -d travora --clean --if-exists /var/backups/travora/travora-YYYY-MM-DD.dump
php artisan migrate --force
php artisan config:cache
php artisan travora:health
sudo supervisorctl restart travora-worker:*
php artisan up
```

Test restores quarterly on a staging database. After restore, confirm wallet ledger totals and dead-letter webhooks before enabling traffic.

Do not restore production data onto a laptop with `APP_DEBUG=true`.

