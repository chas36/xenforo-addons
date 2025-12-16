# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This repository contains XenForo 2.x addons developed under the "Alebarda" namespace. Each addon is a self-contained module following XenForo's addon structure.

## Architecture

### XenForo Addon Structure

Each addon follows the standard XenForo 2.x structure:

```
Alebarda/{AddonName}/
├── addon.json              # Addon metadata (title, version, dependencies)
├── hashes.json             # File integrity hashes
├── Setup.php               # Installation/upgrade/uninstall logic
├── _output/                # JSON definitions exported by XF dev mode
│   ├── bb_code/           # BB code tag definitions
│   └── routes.json        # Route registrations
├── Api/Controller/        # REST API endpoint controllers
├── BbCode/                # BB code rendering callbacks
└── _xf_reference_files/   # XenForo core file references (for development)
```

### Namespace Convention

- Root namespace: `Alebarda\{AddonName}`
- Example: `Alebarda\UserListByGroup\BbCode\UserList`

### Key Architectural Patterns

**BB Code Callbacks**: BB code tags use static callback methods with signature:
```php
public static function render{Name}(
    array $children,
    $option,
    array $tag,
    array $options,
    AbstractRenderer $renderer
)
```

**XenForo Finders**: Use `\XF::finder('XF:EntityName')` to build database queries with method chaining:
- `.where()`, `.whereOr()` for conditions
- `.order()` for sorting
- `.fetch()` or `.fetch(limit)` to execute

**Secondary Group ID Matching**: XenForo stores `secondary_group_ids` as comma-separated strings (e.g., "1,2,3"). To match a group ID, check all positions:
- Exact: `= $groupId`
- Start: `LIKE "$groupId,%"`
- End: `LIKE "%,$groupId"`
- Middle: `LIKE "%,$groupId,%"`

**Entity Collections**: Merge results from multiple finders by converting to arrays, deduplicating by keys, then creating new `\XF\Mvc\Entity\ArrayCollection`.

## Current Addons

### UserListByGroup
Displays users belonging to a specific user group via BB code tags and API endpoints.

**BB Code Tags**:
- `[userlist=GROUP_ID]` - List usernames with links
- `[userlist=GROUP_ID,LIMIT]` - With custom limit (max 100)
- `[avatarlist=GROUP_ID,LIMIT,SIZE]` - Grid of avatars (size: s/m/l/o)

**API Endpoint**:
- `GET /api/users/by-group?group_id={id}&limit={num}`
- Returns: `{ users: [...], total: N, group_id: X, group_title: "..." }`

**Implementation files**:
- `BbCode/UserList.php` - Username list renderer
- `BbCode/AvatarList.php` - Avatar grid renderer
- `Api/Controller/UsersByGroup.php` - API endpoint

### RankedPoll
Work in progress addon for ranked-choice voting in polls. Currently only contains XenForo core reference files for poll functionality.

## Development Workflow

XenForo uses a "development mode" where changes to code are automatically synced to `_output/` JSON files. These JSON files define:
- BB code tag configurations (`_output/bb_code/`)
- Route mappings (`_output/routes.json`)
- Other XenForo data types (permissions, templates, etc.)

**When modifying addon behavior**:
1. Changes to PHP classes take effect immediately
2. Changes to BB code definitions, routes, or other XenForo data require updating corresponding `_output/*.json` files OR rebuilding from XenForo admin panel

**File organization**:
- `_xf_reference_files/` contains copies of core XenForo files for reference during development - these are NOT loaded by XenForo

## XenForo-Specific Patterns

**Global XF facade**: `\XF::app()`, `\XF::finder()`, `\XF::phrase()`, `\XF::em()`

**Router for URL building**:
```php
\XF::app()->router('public')->buildLink('members', $user)
```

**HTML escaping**: Use `htmlspecialchars()` for all user-generated content

**User styling**: Users have `display_style_group_id` which maps to CSS class `username--style{id}` for colored usernames

**Member tooltips**: Add `data-xf-init="member-tooltip"` and `data-user-id="{id}"` to links for hover cards
