# Railway deployment — security posture

This directory contains the Railway deploy config for **test-only** usage.
The instance is intentionally firewalled from any external production system.

## What is blocked from going out

| Integration | How it's blocked |
|---|---|
| **Laravel scheduler** | We run `php artisan serve` only — no `schedule:run`. Scheduler code also guards on `App::environment('production')` and we set `APP_ENV=testing`. |
| **Queue workers** | No `queue:work` is started. `QUEUE_CONNECTION=sync` keeps jobs inline and throwable on-call. |
| **Article → VismaNet sync** | `UpdateArticleJob` is wrapped in `ArticleSyncControl` middleware which releases the job when `is_wgr_active()` returns false. `entrypoint.sh` forces `configs.wgr_is_active = 0` on every boot, overriding any value imported from a production dump. |
| **Email** | Set `MAIL_MAILER=log` so mails are written to stderr instead of sent. |
| **MailerLite / Postmark / Mailgun / SES** | Credentials are expected to be empty in Railway env. The clients throw when unconfigured, which is swallowed as a warning. |
| **Vendora Admin API** | `VENDORA_ADMIN_API_KEY` stays empty in Railway — auth fails early. |
| **Tilde / Visma.net** | `TILDE_API_KEY`, `VISMA_CLIENT_ID`, `VISMA_CLIENT_SECRET` stay empty. |
| **OpenAI / Claude / DeepL** | Credentials stay empty; requests error out before spending tokens. |
| **GS1 / Validoo** | `GS1_API_KEY` is set to `test-key-dev-only` which causes a 401 on first call. Bundle auto-GTIN catches the exception and saves without an EAN. |
| **Sentry** | `SENTRY_LARAVEL_DSN` stays empty; errors stay local. |

## What you *should* set in Railway env

```
APP_KEY=base64:...
APP_ENV=testing           # NOT production — disables scheduler guards
APP_DEBUG=true
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
LOG_CHANNEL=stderr
LOG_LEVEL=info
QUEUE_CONNECTION=sync     # no background processing
MAIL_MAILER=log           # do not send emails
CACHE_STORE=file
SESSION_DRIVER=file
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
GS1_API_KEY=test-key-dev-only
GS1_COMPANY_PREFIX=735016797
```

## What you must NOT set

Anything that gives write access to a production system. Specifically, leave
these variables **empty** in Railway:

- `VISMANET_CLIENT_ID`, `VISMANET_CLIENT_SECRET`, `VISMANET_COMPANY`
- `VENDORA_ADMIN_API_KEY`, `VENDORA_ADMIN_WEB_URL`, `VENDORA_ADMIN_API_URL`
- `TILDE_API_KEY`, `TILDE_API_URL`
- `MAILGUN_SECRET`, `POSTMARK_TOKEN`, `AWS_SECRET_ACCESS_KEY`, `MAILERSEND_API_KEY`
- `OPEN_AI_KEY`, `CLAUDE_KEY`, `DEEPL_API_KEY`
- `SENTRY_LARAVEL_DSN`
- `ALLIANZ_API_KEY`

## If a dump gets imported

The `entrypoint.sh` script runs **every container boot** and:

1. Resets `configs.wgr_is_active` to `0` so the sync kill switch stays off
   even when the imported dump had it set to `1`.
2. Detects pre-existing data via `SELECT COUNT(*) FROM articles > 0` and
   skips migrations + seed to avoid schema drift.

## Known gaps / manual audit items

- If someone manually sets `APP_ENV=production` in Railway, the scheduler
  *could* run — but only if `schedule:run` is started. It isn't. Still, keep
  `APP_ENV=testing`.
- `Article::booted()::saved` and `::updated` hooks dispatch `UpdateArticleJob`
  to the queue. With `QUEUE_CONNECTION=sync` the job runs inline, but
  `ArticleSyncControl` middleware catches it and releases. If someone later
  switches to `database` and starts a `queue:work`, they must verify
  `wgr_is_active = 0` first.
