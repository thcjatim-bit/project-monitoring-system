# Issue #66 — Shared public IPv4 edge handoff

**Status**: Read-only inventory snapshot, 2026-08-18
**Public IPv4**: `103.149.15.22`
**PMS host**: `192.168.150.100`
**Other application host**: `192.168.150.176`

## Boundary

PMS/API public ingress uses the approved PMS ports, currently TCP 80 and 443. The
single public IPv4 address is shared with operator-approved, independently owned
team services. Those existing services are exceptions to any generic
“80/443-only” interpretation and must remain operational.

This document does not authorize a MikroTik change. Before any future network
operation, refresh the RouterOS export and re-confirm ownership. Existing
non-PMS rules always win over a new PMS port proposal.

## RouterOS dst-NAT inventory

The following snapshot came from the operator-provided read-only output of
`/ip firewall nat print detail without-paging`. Active rules are marked `active`;
rules marked `disabled` were left untouched and must not be enabled, removed, or
renumbered by issue #66.

| Public TCP | Internal destination | Current owner / purpose | PMS-owned | Safe to change |
|---:|---|---|:---:|:---:|
| 80 | `192.168.150.100:80` | Active `deploythc.web.id` HTTP | Yes | No, except PMS-owned edge config |
| 443 | `192.168.150.100:443` | Active `deploythc.web.id` HTTPS | Yes | No, except PMS-owned edge config |
| 100 | `192.168.150.100:3000` | Active WAHA | No | No |
| 1080 | `192.168.150.100:80` | Existing unlabelled PMS-host mapping; ownership unresolved | No assumption | No |
| 1996 | `192.168.150.176:22` | Active SSH to Linux server | No | No |
| 3000 | `192.168.150.176:3000` | Active PPPoE BWI application | No | No |
| 3001 | `192.168.150.176:3001` | Active PPPoE MJK | No | No |
| 3002 | `192.168.150.176:3002` | Active ONT Monitoring Jatirejo | No | No |
| 3005 | `192.168.150.176:3005` | Active ONT Monitoring Banyuwangi | No | No |
| 3098 | `172.30.50.3:18789` | Active OpenClaw dashboard | No | No |
| 8000 | `192.168.150.176:8000` | Active Spawnlog | No | No |
| 8080 | `192.168.150.100:8080` | Existing unlabelled PMS-host mapping; listener/ownership unresolved | No assumption | No |
| 8089 | `192.168.150.176:8089` | Active team task service | No | No |
| 8100 | `192.168.150.100:22` | Active SSH administration | No | No |
| 80 | `192.168.150.176:80` | Disabled Bettaspawnlog rule | No | No |
| 8888 | `192.168.150.100:8888` | Disabled New NMS rule | No | No |
| 20128 | `192.168.150.100:20128` | Disabled 9router rule | No | No |

The unlabelled mappings to `192.168.150.100` on public 1080 and 8080 are not
permission to change them. If their ownership is ever questioned, identify the
owner and make a separate PMS-only decision; issue #66 must not use them as a
reason to alter an existing rule.

## Source-IP and database safety evidence

The existing source-NAT table contains a rule labelled `BYPASS masquerade
deploythc` that accepts TCP 80/443 to `192.168.150.100` on the approved WAN
interface before the generic masquerade rule. Preserve both that bypass and the
generic masquerade behavior.

The RouterOS dst-NAT export contains no public rule for TCP 5432 or TCP 5433.
Production PostgreSQL is locally bound to `127.0.0.1:5433`; the testing/Docker
listener is on an internal Docker bridge at `172.17.0.1:5432`. Neither database
port may be added to public NAT or WAN firewall rules.

## API edge decision

`api.deploythc.web.id` must not receive a new public port. After issue #52 has
implemented and verified the API routes, it should share the existing Nginx
listeners:

```text
103.149.15.22:80  -> 192.168.150.100:80  -> Nginx server_name api.deploythc.web.id
103.149.15.22:443 -> 192.168.150.100:443 -> Nginx server_name api.deploythc.web.id
```

The existing `deploythc.web.id` behavior must remain unchanged. Laravel and
PHP-FPM remain private behind Nginx.

## Read-only verification

Run these commands through the approved authorities; they do not modify router
state:

```routeros
/ip firewall nat print detail without-paging
/ip firewall nat print detail where chain=dstnat
/ip firewall filter print detail without-paging
```

On `pms-prod`, collect sanitized host evidence:

```sh
sudo -n ss -H -lntup
sudo -n nginx -T
sudo -n ufw status verbose
sudo -n nft list ruleset
```

From an independent WAN vantage point, verify the database negative boundary
and the PMS health path. Do not use production credentials or API keys in these
checks:

```powershell
Test-NetConnection 103.149.15.22 -Port 5432 -InformationLevel Quiet
Test-NetConnection 103.149.15.22 -Port 5433 -InformationLevel Quiet
curl.exe -fsS -o NUL -D - https://deploythc.web.id/up
```

## Rollback boundary

Issue #66 makes no router change. If a future API vhost or DNS record is
introduced, remove only the API-specific record and vhost, reload through the
approved infrastructure authority, and leave every existing team-owned
dst-NAT/filter rule intact. Never use this handoff to “clean up” shared-IP
services.
