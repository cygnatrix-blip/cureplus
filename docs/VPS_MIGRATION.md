# Moving to a VPS

For whoever moves CurePlus off shared hosting. No application code has to change; this is configuration and server setup.

## When to move

- Queue delay matters: cron starts the worker only once a minute.
- Custom domains approach the 100 parked-domain limit, or manual parking becomes a burden.
- Page times suffer against the 60 entry-process limit.

## Steps

1. **Server:** PHP 8.4 FPM with `pdo_mysql`, `gd`, `intl`, `gmp`, `mbstring`, `zip`; MariaDB 10.5 or newer; Redis; Supervisor; Node for builds.
2. **Copy data:** dump the database and copy `storage/app`. Bring the same `APP_KEY`, or encrypted columns cannot be read.
3. **Switch drivers in `.env`:**

   ```dotenv
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   ```

   Keep `SESSION_DRIVER=tracked-database`. Moving sessions to Redis would remove the signed-in device list and forced logout, because those read the `sessions` table.
4. **Workers:** run them under Supervisor, then remove the `queue-worker` entry from `routes/console.php`, along with the assertion on it in `PlatformFoundationTest`. Keep cron for the scheduler.

   ```ini
   [program:cureplus-worker]
   command=php /var/www/cureplus/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   numprocs=2
   process_name=%(program_name)s_%(process_num)02d
   autostart=true
   autorestart=true
   stopwaitsecs=120
   user=www-data
   ```

5. **Deploys:** `BUILD_ASSETS=1 bash deploy.sh`. `queue:restart` in the script reloads the workers.
6. **DNS:** lower the TTL a day ahead. Update `PLATFORM_IPV4` and `PLATFORM_IPV6` in `.env`. Organizations using root domains (A records) must change their records, so warn them early. CNAME subdomains follow automatically through `CONNECT_HOST`.

## Automatic custom domains

On shared hosting, an admin parks each domain by hand. A VPS removes that step.

**Caddy on-demand TLS** (self-hosted):

```caddyfile
{
    on_demand_tls {
        ask http://127.0.0.1/internal/domains/allowed
    }
}

https:// {
    tls {
        on_demand
    }
    reverse_proxy unix//run/php/cureplus.sock
}
```

The `ask` endpoint must approve only verified domains from `custom_domains`. Without that check, anyone pointing a domain at the server could use up the certificate rate limits.

**Cloudflare for SaaS** (managed): create a custom hostname per organization through the API. This suits you if you would rather not run certificates yourself.
