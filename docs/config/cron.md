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
| `billing:daily` | daily, early morning | Issues renewal invoices, sends billing reminders, suspends schools whose access ran out, expires unfinished payments |

## Queue worker

```
* * * * * cd /path/to/simas && php artisan queue:work --sleep=2 --max-time=50 >> /dev/null 2>&1
```

- `QUEUE_CONNECTION=database`. Without this job, messages stay in
  "Dalam antrean".
- A short-lived worker per minute never overlaps the next run and needs
  no `queue:restart` after a deploy.
- WhatsApp messages are paced per school (random pause of
  `OPENWA_PACE_MIN`–`OPENWA_PACE_MAX` seconds, `OPENWA_DAILY_LIMIT` a
  day), so a burst such as the morning gate can take tens of minutes to
  leave; a held-back message is put back in the queue and a job lives for
  up to 12 hours. A held-back job is a delayed job, which
  `--stop-when-empty` does not wait for: with it the worker would exit at
  once and send only about one message per school per minute. So the
  worker has no `--stop-when-empty`: it idles with `--sleep=2` and picks
  the delayed jobs up as they come due, until `--max-time=50` ends it. At
  about 6.5 s per message that is 7 to 8 messages per school per run, so
  500 gate messages take about an hour.
- Hostinger documents no cron time limit (PHP `max_execution_time` is 360 s
  on Web plans; a plan may be throttled when CPU or memory limits are
  reached), so test on the real plan before relying on it: send about 20
  test messages to one school and check they leave within 2 to 3 minutes. If
  the worker is cut off, lower `--max-time` (for example 30).
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

## Billing

```
10 3 * * * cd /path/to/simas && php artisan billing:daily >> /dev/null 2>&1
```

- Runs once a day, early in the morning Jakarta time. The "day" it works on
  is the date in `BILLING_TIMEZONE` (default `Asia/Jakarta`), whatever
  timezone the server's cron uses.
- Four steps, in order, each on its own (one that fails does not stop the
  next): renewal invoices (14 days before a paid period ends), reminders
  (trial ending, invoice due, invoice late), suspension (access ran out,
  grace included), and expiry of gateway payments nobody finished.
- Safe to run twice on the same day and to run late: reminders are recorded
  per day in `billing_notices`, and a late run sends only the newest
  reminder that still makes sense.
- Check before trusting it: `php artisan billing:daily --dry-run` lists what
  it would do and changes nothing. `--date=YYYY-MM-DD` runs as if it were
  that day (replay a missed day, or look ahead together with `--dry-run`),
  and `--only=renewals` (repeatable) runs single steps.
- A guard stops one run from suspending more than `billing.suspend_cap`
  schools. It then suspends nobody, exits with an error, and the billing
  overview in the console says so; after checking the list, repeat with
  `--force`.
- The console's billing overview shows when the job last finished a full
  run and turns red after `billing.cron_stale_days` days without one. A dry
  run, a single step, a `--date` run or a run with a failed step does not
  count as "last run".
- Needs the queue worker above: reminders and invoices are queued emails.

## Adding a job

1. Add the crontab line and a short "why" under its own heading above.
2. Add a row to the table at the top.
3. Make the command safe to run twice in a row and late (cron on shared
   hosting can be delayed).
