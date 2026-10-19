# Shoulder Rehab (PWA)

Static app: `index.html`, `manifest.webmanifest`, `sw.js`, `icons/`. No build step.
The log is saved on the device first and syncs to your own Supabase table when online.

## 1. Supabase (5 min, free tier)
1. supabase.com, then New project (any name and region).
2. SQL Editor, then New query: paste `supabase.sql` and Run.
3. Project Settings, then API: copy the **Project URL** and the **anon** or **publishable** key.

The anon key is public by design. Rows are filtered by row level security against a
private sync key that the app sends as a header, so the anon key alone can't read or write anything.

## 2. Host it (GitHub Pages)
1. Make a new repo (e.g. `rehab`) and upload the contents of this folder to the root.
2. Settings, then Pages: Deploy from branch, `main`, `/ (root)`.
3. The app is at `https://<username>.github.io/rehab/`. It needs HTTPS, which Pages gives you.

Any static host works (Netlify, Cloudflare Pages, a folder on your own site).

## 3. Install on the iPhone
1. Open the URL in **Safari**, tap Share, then **Add to Home Screen**.
2. Open it **from the home screen icon** (the installed app keeps its own storage, separate from Safari).
3. Log tab, then Sync settings: paste the URL and key, tap **Generate**, then **Save and sync**.

## 4. Second device
In the app on device one: Log, then Sync settings, then **Copy setup code**.
On device two: paste it in **Paste setup code**, then tap **Connect**. The setup code contains your sync key, so keep it private.

## Updating
Change files, then bump `VERSION` in `sw.js`. Installed copies pick up the new version
the next time they open with a connection (the page tries the network for 3 s, then falls back to the cached copy).

## Self-hosted backend (instead of Supabase)
`server/` is a PHP + SQLite stand-in for the Supabase REST calls the app makes, deployed to
`~/experiments.cwandt.com/rehab-api/` on the CW&T VPS (`scp server/.htaccess server/api.php`).
In Sync settings use URL `https://www.experiments.cwandt.com/rehab-api` and the key in
`rehab-api/dbs/anon_key.txt` on the server. The database is `rehab-api/dbs/rehab.sqlite`.
