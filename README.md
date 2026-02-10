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
- `label` (optional) Override label.
- `instructions` (optional) Override instructions.
- `required` (optional) Override required flag (`true`/`false`).
- `tip` (optional) Override tip text.
- `warning` (optional) Override warning text.

Notes
- The `as` handle must be unique within the entry type layout.
- Handle overrides are validated against Craft’s handle rules and reserved words.

### Add a tab to an entry type

```bash
php craft command-line/entry-types/add-tab myEntryTypeHandle "Tab Name"
```

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
