-- Shoulder Rehab sync table. Paste into Supabase: SQL Editor -> New query -> Run.
-- Rows are only visible to requests that send the matching x-sync-key header,
-- so the public anon/publishable key alone cannot read or change your log.

create table if not exists public.events (
  id          text primary key,
  space       text not null,
  type        text not null check (type in ('ex', 'pain')),
  ex          text,
  p           int,
  t           text,
  ts          bigint not null,
  deleted     boolean not null default false,
  updated_at  timestamptz not null default clock_timestamp()
);

create index if not exists events_space_updated on public.events (space, updated_at);

create or replace function public.events_touch() returns trigger
language plpgsql as $$
begin
  new.updated_at := clock_timestamp();
  return new;
end $$;

drop trigger if exists events_touch on public.events;
create trigger events_touch before insert or update on public.events
  for each row execute function public.events_touch();

alter table public.events enable row level security;

drop policy if exists "rows for this sync key" on public.events;
create policy "rows for this sync key" on public.events
  for all to anon
  using (
    length(space) >= 24
    and space = coalesce(current_setting('request.headers', true)::json ->> 'x-sync-key', '')
  )
  with check (
    length(space) >= 24
    and space = coalesce(current_setting('request.headers', true)::json ->> 'x-sync-key', '')
  );

grant select, insert, update on public.events to anon;
