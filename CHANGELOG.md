# Release Notes for command-line

## Unreleased
- Added `command-line/fields/create` for creating new custom fields of any type.
- Fixed `command-line/entries/create` on Craft 5: the owning section is now resolved from the entry type (entry types no longer expose `sectionId`). Added `--section` to choose a section when an entry type is used by more than one.

## 1.0.0
- Initial release
