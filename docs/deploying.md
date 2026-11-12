# Deploying

The live site is a **git checkout**, not an upload. It sits at
`/home4/schooics/muhindomubaraka` on the cPanel account `schooics`, reachable
over SSH on port 22. Credentials are in `credentials.txt` at the repo root,
which git ignores and which must never be committed.

The server has `php` 8.3, `git`, `mysql`, `mysqldump`, `rsync` and `tar`. It has
**no composer and no node**. So:

- a change to `composer.json` means uploading `vendor/` by hand,
- a change to `resources/css`, `resources/js` or `tailwind.config.js` means
  running `npm run build` locally and uploading `public/build`, which git
  ignores.

Neither is usually needed. Nothing currently rendered loads the Vite bundle:
only `layouts/guest.blade.php` calls `@vite`, and no view extends it. The real
stylesheets are committed static files under `public/css` and `public/vendor`.

## The procedure

SSH in forcing password auth, or the server rejects the connection after
offering local keys:

```
sshpass -p "$CPW" ssh -p 22 -o PreferredAuthentications=password \
  -o PubkeyAuthentication=no -o IdentitiesOnly=yes schooics@muhindomubaraka.com
```

Then, with everything already pushed to `origin/master`:

```
cd /home4/schooics/muhindomubaraka
mkdir -p ~/predeploy-backup-$(date +%Y%m%d)
mysqldump -u<user> -p<pass> --single-transaction --routines --triggers \
  schooics_muhindo | gzip -9 > ~/predeploy-backup-$(date +%Y%m%d)/db.sql.gz
tar -czf ~/predeploy-backup-$(date +%Y%m%d)/app-tree.tar.gz \
  --exclude=./vendor --exclude=./node_modules --exclude=./storage/framework/cache .

php artisan down --retry=30
git fetch origin && git reset --hard origin/master
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache \
  && php artisan route:cache && php artisan view:cache
php artisan up
```

Take the backup every time. It is two minutes and 39MB, and it is the only
route back from a migration that drops a column.

**Routes and config are cached in production.** A change to `routes/`, `config/`
or any `.env` value does nothing at all until `route:cache` and `config:cache`
are rebuilt. This is the most likely reason a deployed change appears to have
had no effect.

## Confirming it worked

Hit the pages rather than trusting the absence of an error:

```
for p in / /courses /shop /insights /login /register; do
  curl -s -o /dev/null -w "$p %{http_code}\n" https://muhindomubaraka.com$p
done
tail -25 storage/logs/laravel.log
```

Admin paths must answer `302` to `/login` when not signed in, never `200`.

To exercise real business logic against production data without leaving a
trace, put the calls in a transaction and roll it back, or use a method built
not to persist, such as `CouponService::quote()`:

```
php artisan tinker /path/to/check.php     # DB::beginTransaction() … DB::rollBack()
```

## History worth knowing

Before November 2026 deploys were made by uploading a zip and a self-deleting
`deploy.php`. The live tree therefore drifted 134 files ahead of its own git
HEAD on a branch called `production-sync`, and nobody could say from the
outside what was actually running. On 2026-11-12 the tree was verified file by
file against local git history — every file matched a known blob, nothing had
been edited only on the server — and the checkout was moved onto `master`.
Keep it there, and deploy by pulling.
