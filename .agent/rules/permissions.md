---
name: shell_permissions
description: Erlaubt cat, blockiert gefährliche Befehle
usage: global
---

# Agent Permissions

## Allow List
- command(cat)
- command(ls)
- command(mkdir)
- command(php -v)
- command(find)
- read_file(*)

## Deny List
- command(rm)
- command(sudo)
- command(chmod)
- command(chown)
- write_file(/etc/hosts)

## Ask List
- command(*)
