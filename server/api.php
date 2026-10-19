<?php
// Minimal stand-in for the slice of Supabase/PostgREST that index.html uses:
//   POST ?on_conflict=id   upsert rows (merge-duplicates)
//   GET  ?updated_at=gt.<cursor>&order=updated_at.asc&limit=N
// Same rules as supabase.sql: a row is visible only when space == x-sync-key (min 24 chars).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: apikey, x-sync-key, content-type, prefer, authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json');

function fail($code, $msg) { http_response_code($code); echo json_encode(['message' => $msg]); exit; }

$dbs = __DIR__ . '/dbs';
$anon = trim(@file_get_contents("$dbs/anon_key.txt"));
if ($anon === '' || !hash_equals($anon, $_SERVER['HTTP_APIKEY'] ?? '')) fail(401, 'Invalid API key');
$space = $_SERVER['HTTP_X_SYNC_KEY'] ?? '';
if (strlen($space) < 24) { if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo '[]'; exit; } fail(403, 'x-sync-key required'); }

$db = new PDO("sqlite:$dbs/rehab.sqlite", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout = 5000');
$db->exec("create table if not exists events (
  id text primary key, space text not null, type text not null check (type in ('ex','pain')),
  ex text, p int, t text, ts int not null, deleted int not null default 0, updated_at text not null)");
$db->exec('create index if not exists events_space_updated on events (space, updated_at)');

// Fixed-width UTC microsecond timestamps, so string comparison == time order.
function now() { $d = DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true))); return $d->format('Y-m-d\TH:i:s.u\Z'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rows = json_decode(file_get_contents('php://input'), true);
  if (!is_array($rows)) fail(400, 'Expected JSON');
  if (isset($rows['id'])) $rows = [$rows];
  // Rows owned by another space are left untouched (RLS would reject them).
  $st = $db->prepare('insert into events (id, space, type, ex, p, t, ts, deleted, updated_at)
    values (?, ?, ?, ?, ?, ?, ?, ?, ?)
    on conflict(id) do update set type=excluded.type, ex=excluded.ex, p=excluded.p, t=excluded.t,
      ts=excluded.ts, deleted=excluded.deleted, updated_at=excluded.updated_at
    where events.space = excluded.space');
  $db->beginTransaction();
  try {
    foreach ($rows as $r) {
      if (!is_array($r) || !is_string($r['id'] ?? null) || ($r['space'] ?? $space) !== $space) fail(403, 'Row space must match x-sync-key');
      if (!in_array($r['type'] ?? '', ['ex', 'pain'], true) || !is_numeric($r['ts'] ?? null)) fail(400, 'Bad row');
      $st->execute([$r['id'], $space, $r['type'], $r['ex'] ?? null, isset($r['p']) ? (int)$r['p'] : null,
        $r['t'] ?? null, (int)$r['ts'], empty($r['deleted']) ? 0 : 1, now()]);
    }
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  http_response_code(201);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed');
$after = preg_replace('/^gt\./', '', $_GET['updated_at'] ?? '');
// Normalize the cursor to the stored format; the app starts from 1970-01-01T00:00:00Z.
$t = strtotime($after) !== false ? new DateTime($after) : new DateTime('@0');
$after = $t->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
$limit = max(1, min(1000, (int)($_GET['limit'] ?? 1000)));
$st = $db->prepare('select id, type, ex, p, t, ts, deleted, updated_at from events
  where space = ? and updated_at > ? order by updated_at asc limit ?');
$st->execute([$space, $after, $limit]);
$out = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $r['p'] = $r['p'] === null ? null : (int)$r['p'];
  $r['ts'] = (int)$r['ts'];
  $r['deleted'] = (bool)$r['deleted'];
  $out[] = $r;
}
echo json_encode($out);
