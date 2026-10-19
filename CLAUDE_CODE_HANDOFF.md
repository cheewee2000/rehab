# Handoff: deploy the Shoulder Rehab PWA

You are deploying a finished static web app. Do not redesign or refactor it. The job is to put it on
GitHub Pages, get a working HTTPS link, and (if possible) set up the Supabase sync backend.

## What's in this folder

| File | Purpose |
|---|---|
| `index.html` | The whole app (HTML, CSS, JS inline). Local-first log in `localStorage`, syncs to Supabase over REST. |
| `manifest.webmanifest` | PWA manifest. `start_url` and `scope` are `./`, so it works from a subpath like `/rehab/`. |
| `sw.js` | Service worker. Caches the app shell for offline use; never caches Supabase traffic. |
| `icons/` | 192, 512, maskable 512, and 180 apple-touch icons. |
| `supabase.sql` | Creates the `events` table, trigger, and row level security policy. |
| `README.md` | Human setup notes. |

There is no build step and no dependencies. Serve the files as is.

## Task 1: GitHub repo and Pages (required)

1. Confirm `gh auth status` works. GitHub user is `cheewee2000`.
2. Create a **public** repo named `rehab` (Pages on a free plan needs public; if the user has Pro, private is fine. Ask if unsure):
   `gh repo create cheewee2000/rehab --public --description "Shoulder rehab PWA"`
3. From this folder: `git init`, add everything, commit, set branch `main`, add the remote, push.
   Leave `CLAUDE_CODE_HANDOFF.md` in the repo or delete it; either is fine.
4. Add an empty `.nojekyll` file at the root and push, so Pages serves files as is.
5. Enable Pages from the `main` branch root:
   `gh api -X POST repos/cheewee2000/rehab/pages -f "source[branch]=main" -f "source[path]=/"`
   (If it returns 409 because Pages already exists, use `-X PUT` with the same fields.)
6. Poll until it's built: `gh api repos/cheewee2000/rehab/pages --jq .status` until `built` (usually 1 to 2 min).
7. Verify with curl, all must return 200:
   - `https://cheewee2000.github.io/rehab/`
   - `https://cheewee2000.github.io/rehab/manifest.webmanifest`
   - `https://cheewee2000.github.io/rehab/sw.js`
   - `https://cheewee2000.github.io/rehab/icons/apple-touch-icon.png`
8. Report the link to the user: **https://cheewee2000.github.io/rehab/**

If the repo name `rehab` is taken, use `shoulder-rehab` and adjust the URLs.

## Task 2: Supabase backend (do it if you have access, otherwise hand the user the steps)

The app works without this; the log just stays on one device. With it, phone and laptop share one log.

**If you have the Supabase CLI logged in or a Supabase MCP connector:**
1. Create a project (free tier, any region near New York, e.g. `us-east-1`). Name: `rehab`.
2. Run `supabase.sql` against it (SQL editor, `supabase db query`, or the MCP's execute SQL tool).
3. Fetch the project URL (`https://<ref>.supabase.co`) and the **anon** or **publishable** API key.
4. Sanity check RLS with curl. Without the header it must return `[]` or an error, never rows:
   ```
   curl "https://<ref>.supabase.co/rest/v1/events?select=id" -H "apikey: <anon key>"
   ```
   With a 48-char hex `x-sync-key` header, a POST of one row then a GET must return that row.
   Delete the test row afterwards (PATCH it to `deleted=true` is fine; the anon role has no DELETE grant).
5. Give the user the URL and anon key. **Do not** generate or choose the sync key for them; the app generates it on the phone.
6. Never commit keys to the repo. The app stores them in the device's local storage only.

**If you have no Supabase access,** tell the user to:
1. Create a free project at supabase.com.
2. Open SQL Editor, paste `supabase.sql`, Run.
3. Project Settings → API: copy the Project URL and anon/publishable key.

## Task 3: Tell the user how to install (just relay this)

1. On the iPhone, open the link in **Safari** → Share → **Add to Home Screen**.
2. Open the app **from the home screen icon** (it has storage separate from Safari).
3. Log tab → Sync settings → paste URL and key → **Generate** → **Save and sync**.
4. Laptop or second device: in the phone app tap **Copy setup code**, then paste it into **Paste setup code** on the other device and tap **Connect**.

## How sync works (for debugging only)

- Table `public.events`: `id text pk, space text, type ('ex'|'pain'), ex, p, t, ts bigint, deleted bool, updated_at timestamptz`.
- `space` is the private sync key. RLS allows a row only when `space` equals the request header `x-sync-key` (min 24 chars).
- Client pushes dirty rows with `POST /rest/v1/events?on_conflict=id` and `Prefer: resolution=merge-duplicates`,
  then pulls `?updated_at=gt.<cursor>&order=updated_at.asc`. Deletes are soft (`deleted=true`).
- Sends `Authorization: Bearer` only when the key is a JWT (`eyJ...`); new `sb_publishable_` keys go in `apikey` only.

## Updating later

After any file change, bump `VERSION` in `sw.js` (e.g. `rehab-v2`) before pushing, so installed copies refresh.
