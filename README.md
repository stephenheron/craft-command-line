# command-line



## Requirements

This plugin requires Craft CMS 5.8.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “command-line”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require whereverly/craft-command-line

# tell Craft to install the plugin
./craft plugin/install command-line
```

## Commands

### List all fields

```bash
php craft command-line/fields/get
```

### List all entry types

```bash
php craft command-line/entry-types/list
```

Lists all entry types with their handle, name, and associated sections.

### List fields for an entry type

```bash
php craft command-line/entry-types/fields myEntryTypeHandle
```

This includes custom fields, native fields, and UI elements (Heading, Tip, Markdown, Template, etc.).

### Add fields to an entry type

```bash
php craft command-line/entry-types/add-fields myEntryTypeHandle \
  --fields-config='[
    {"handle":"text","as":"introText","label":"Intro","required":true,"after":"existingFieldHandle"},
    {"handle":"text","as":"outroText","label":"Outro"}
  ]'
```

Options
- `--tab="Tab Name"` (optional) Choose the tab to add fields to. Defaults to the first tab. If no tabs exist, a new `Content` tab is created.
- `--fields-config` (required) JSON array of field additions.

Field config keys
- `handle` (required) Existing field handle to add.
- `as` (optional) Instance handle override (useful for multi-instance fields).
- `after` (optional) Insert after the given handle in the tab. If not found, fields are appended.
- `before` (optional) Insert before the given handle in the tab. Cannot be used with `after`.
- `label` (optional) Override label.
- `instructions` (optional) Override instructions.
- `required` (optional) Override required flag (`true`/`false`).
- `tip` (optional) Override tip text.
- `warning` (optional) Override warning text.
- `elementCondition` (optional) Element condition config (array) controlling when the field is shown, or `null` to leave unset. See Craft's element condition docs.

Notes
- The `as` handle must be unique within the entry type layout.
- Handle overrides are validated against Craft's handle rules and reserved words.

### Edit fields on an entry type

```bash
php craft command-line/entry-types/edit-fields myEntryTypeHandle \
  --fields-config='[
    {"handle":"metaTitle","label":"New Label","required":true}
  ]'
```

Options
- `--tab="Tab Name"` (optional) Limit edits to a specific tab. Defaults to searching all tabs.
- `--fields-config` (required) JSON array of field edits.

Field config keys
- `handle` (required) The field handle to find and edit.
- `label` (optional) New label.
- `instructions` (optional) New instructions.
- `required` (optional) New required flag (`true`/`false`).
- `tip` (optional) New tip text.
- `warning` (optional) New warning text.
- `as` (optional) Rename the instance handle.
- `tab` (optional) Move the field to a different tab.
- `after` (optional) Reposition after the given handle.
- `before` (optional) Reposition before the given handle. Cannot be used with `after`.
- `elementCondition` (optional) New element condition config (array), or `null` to clear an existing condition.

### Remove fields from an entry type

```bash
php craft command-line/entry-types/remove-fields myEntryTypeHandle \
  --fields-config='[
    {"handle":"text"},
    {"handle":"text","as":"introText"}
  ]'
```

Options
- `--tab="Tab Name"` (optional) Limit removals to a specific tab.

### Add a tab to an entry type

```bash
php craft command-line/entry-types/add-tab myEntryTypeHandle "Tab Name"
```

### Remove a tab from an entry type

```bash
php craft command-line/entry-types/remove-tab myEntryTypeHandle "Tab Name"
```

The tab must be empty (no fields or UI elements) before it can be removed.

### Rename a tab on an entry type

```bash
php craft command-line/entry-types/edit-tab myEntryTypeHandle "Old Name" --name="New Name"
```

### Add UI elements to an entry type

```bash
php craft command-line/entry-types/add-ui-elements myEntryTypeHandle \
  --elements-config='[
    {"type":"heading","heading":"Page Settings","after":"fieldHandle"},
    {"type":"hr"},
    {"type":"tip","tip":"Helpful info","style":"tip","dismissible":false},
    {"type":"markdown","content":"**Bold text** here"},
    {"type":"line-break"}
  ]'
```

Options
- `--tab="Tab Name"` (optional) Choose the tab to add elements to. Defaults to the first tab.
- `--elements-config` (required) JSON array of UI element definitions.

Supported element types
- `heading` — Config: `{"type":"heading","heading":"Text here"}`
- `hr` / `horizontal-rule` — Config: `{"type":"hr"}`
- `tip` — Config: `{"type":"tip","tip":"Text","style":"tip|warning","dismissible":true|false}`
- `markdown` — Config: `{"type":"markdown","content":"Markdown text"}`
- `line-break` / `br` — Config: `{"type":"line-break"}`

Each element supports `after` or `before` for positioning relative to a field handle. Elements without positioning are appended, or inserted after the previously inserted element.

### Remove UI elements from an entry type

```bash
php craft command-line/entry-types/remove-ui-elements myEntryTypeHandle \
  --elements-config='[
    {"type":"heading","heading":"Page Settings"},
    {"type":"hr"}
  ]'
```

Options
- `--tab="Tab Name"` (optional) Limit removals to a specific tab. Defaults to searching all tabs.
- `--elements-config` (required) JSON array of UI elements to match and remove.

For `heading`, `tip`, and `markdown` types, you can provide the text content to match a specific element. Without it, all elements of that type are removed.

### Create an entry

```bash
php craft command-line/entries/create myEntryTypeHandle \
  --title="My Title" \
  --slug="my-title" \
  --site=default \
  --status=enabled \
  --fields='{"fieldHandle":"value"}'
```

Options
- `--title` (required when the entry type has a title field)
- `--slug` (optional)
- `--site` (optional, defaults to primary site handle)
- `--status` (optional: `enabled` or `disabled`)
- `--fields` (optional JSON object of field handles and values)
