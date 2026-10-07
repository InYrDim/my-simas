# Cron jobs

Every cron job the app needs on the server. Add a new job here the moment
one is introduced. Target: Hostinger shared hosting, so no daemon and no
`schedule:run` (it needs `proc_open`, often disabled there). Each job is
a plain `php artisan` command with its own crontab line.

Replace `/path/to/simas` with the app's directory on the server.

| Job | Schedule | Why it must run |
| --- | --- | --- |
| `queue:work` | every minute | Sends queued mail and WhatsApp messages |
| `cache:prune-expired` | daily, 03:00 | Keeps the database cache table small |

## Queue worker

```
* * * * * cd /path/to/simas && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

- `QUEUE_CONNECTION=database`. Without this job, messages stay in
  "Dalam antrean".
- A short-lived worker per minute never overlaps the next run and needs
  no `queue:restart` after a deploy.
- Messages leave up to about a minute late. Design queued work so it
  survives that: no sub-minute delivery, no daemon, no Horizon/Redis.
- `QUEUE_CONNECTION=sync` is for trying things out only (no retries; many
  notices at once can hit the execution time limit).
- In development `composer run dev` starts `queue:listen`.

## Cache prune

```
0 3 * * * cd /path/to/simas && php artisan cache:prune-expired >> /dev/null 2>&1
```

- `CACHE_STORE=database` removes an expired row only when its key is read
  again. Keys that are never read again (an unused QR code, for example)
  would pile up in the `cache` table.
- The command (Platform) deletes rows whose `expiration` has passed. It
  does nothing when the cache store is not `database`.
- `cache_locks` needs no job: Laravel clears it by itself.

## Adding a job

1. Add the crontab line and a short "why" under its own heading above.
2. Add a row to the table at the top.
3. Make the command safe to run twice in a row and late (cron on shared
   hosting can be delayed).
