<?php

namespace whereverly\craftcommandline\console\controllers;

use Craft;
use craft\console\Controller;
use craft\base\FieldInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\BaseField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\validators\HandleValidator;
use yii\console\ExitCode;

class EntryTypesController extends Controller
{
    public ?string $tab = null;
    public ?string $fieldsConfig = null;
    public ?string $elementsConfig = null;
    public ?string $name = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'add-fields') {
            $options = array_merge($options, ['tab', 'fieldsConfig']);
        }

        if ($actionID === 'remove-fields') {
            $options = array_merge($options, ['tab', 'fieldsConfig']);
        }

        if ($actionID === 'add-ui-elements') {
            $options = array_merge($options, ['tab', 'elementsConfig']);
        }

        if ($actionID === 'remove-ui-elements') {
            $options = array_merge($options, ['tab', 'elementsConfig']);
        }

        if ($actionID === 'edit-fields') {
            $options = array_merge($options, ['tab', 'fieldsConfig']);
        }

        if ($actionID === 'edit-tab') {
            $options = array_merge($options, ['name']);
        }

        return $options;
    }

    /**
     * List all entry types with their handle, name, and sections.
     *
     * Usage: php craft command-line/entry-types/list
     */
    public function actionList(): int
    {
        $allEntryTypes = Craft::$app->getEntries()->getAllEntryTypes();

        if (empty($allEntryTypes)) {
            $this->stdout("No entry types found.\n");
            return ExitCode::OK;
        }

        $sections = Craft::$app->getEntries()->getAllSections();
        $sectionsByEntryType = [];
        foreach ($sections as $section) {
            foreach ($section->getEntryTypes() as $entryType) {
                $sectionsByEntryType[$entryType->id][] = $section->name;
            }
        }

        $this->stdout(str_pad('Handle', 30) . str_pad('Name', 30) . "Sections\n");
        $this->stdout(str_repeat('-', 90) . "\n");

        foreach ($allEntryTypes as $entryType) {
            $sectionNames = $sectionsByEntryType[$entryType->id] ?? [];
            $this->stdout(
                str_pad($entryType->handle, 30) .
                str_pad($entryType->name, 30) .
                (empty($sectionNames) ? '(none)' : implode(', ', $sectionNames)) .
                "\n"
            );
        }

        $this->stdout("\nTotal: " . count($allEntryTypes) . " entry types\n");

        return ExitCode::OK;
    }

    /**
     * Get fields for a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/fields myEntryTypeHandle
     */
    public function actionFields(string $handle): int
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();

        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();

        if (empty($tabs)) {
            $this->stdout("Entry type {$handle} has no field layout tabs.\n");
            return ExitCode::OK;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Tabs: " . count($tabs) . "\n\n");

        foreach ($tabs as $tab) {
            $this->stdout("Tab: {$tab->name}\n");

            foreach ($tab->getElements() as $element) {
                if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                    $this->outputCustomField($element);
                    $this->stdout("\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\Tip) {
                    $this->stdout("Tip:\n");
                    $this->stdout("  Text: " . ($element->tip ?: '(empty)') . "\n");
                    $this->stdout("  Style: {$element->style}\n");
                    $this->stdout("  Dismissible: " . ($element->dismissible ? 'yes' : 'no') . "\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\Heading) {
                    $this->stdout("Heading:\n");
                    $this->stdout("  " . ($element->heading ?: '(empty)') . "\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\Markdown) {
                    $this->stdout("Markdown:\n");
                    $this->stdout("  Content: " . ($element->content ?: '(empty)') . "\n");
                    $this->stdout("  Display in pane: " . ($element->displayInPane ? 'yes' : 'no') . "\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\Template) {
                    $this->stdout("Template:\n");
                    $this->stdout("  Template: " . ($element->template ?: '(none)') . "\n");
                    $this->stdout("  Template mode: {$element->templateMode}\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\HorizontalRule) {
                    $this->stdout("Horizontal rule\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\LineBreak) {
                    $this->stdout("Line break\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\Html) {
                    $this->stdout("HTML element\n\n");
                    continue;
                }

                if ($element instanceof \craft\fieldlayoutelements\BaseNativeField) {
                    $this->outputNativeField($element);
                    $this->stdout("\n");
                    continue;
                }

                $this->stdout("Element: " . get_class($element) . "\n\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Add fields to a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/add-fields myEntryTypeHandle --fields-config='[{"handle":"text","as":"textOne","label":"Intro"},{"handle":"text","as":"textTwo","label":"Outro"}]'
     * Optional: --fields-config='[{"handle":"text","as":"textOne","label":"Intro"},{"handle":"text","as":"textTwo","label":"Outro"}]'
     */
    public function actionAddFields(string $handle): int
    {
        if (!$this->fieldsConfig) {
            $this->stdout("Missing --fields-config option.\n");
            return ExitCode::USAGE;
        }

        $decoded = json_decode($this->fieldsConfig, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->stdout("Invalid JSON for --fields-config: " . json_last_error_msg() . "\n");
            return ExitCode::DATAERR;
        }
        if (!is_array($decoded)) {
            $this->stdout("--fields-config must decode to a list of objects.\n");
            return ExitCode::DATAERR;
        }

        $fieldEntries = [];
        foreach ($decoded as $index => $entry) {
            if (!is_array($entry) || empty($entry['handle']) || !is_string($entry['handle'])) {
                $this->stdout("--fields-config entry {$index} must include a string handle.\n");
                return ExitCode::DATAERR;
            }
            $overrides = $entry;
            unset($overrides['handle']);
            $fieldEntries[] = [
                'fieldHandle' => $entry['handle'],
                'overrides' => $overrides,
            ];
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
        $tabs = $fieldLayout->getTabs();
        $tabName = $this->tab ?: 'Content';
        $targetTabIndex = 0;

        if (empty($tabs)) {
            $tabs = [new FieldLayoutTab(['name' => $tabName])];
            $targetTabIndex = 0;
        } elseif ($this->tab) {
            $targetTabIndex = null;
            foreach ($tabs as $index => $tab) {
                if ($tab->name === $this->tab) {
                    $targetTabIndex = $index;
                    break;
                }
            }

            if ($targetTabIndex === null) {
                $tabs[] = new FieldLayoutTab(['name' => $this->tab]);
                $targetTabIndex = count($tabs) - 1;
            }
        }

        $fieldLayout->setTabs($tabs);
        $tabs = $fieldLayout->getTabs();
        $targetTab = $tabs[$targetTabIndex] ?? $tabs[0];

        $existingFieldUids = [];
        foreach ($fieldLayout->getCustomFieldElements() as $layoutElement) {
            $existingFieldUids[$layoutElement->getFieldUid()] = true;
        }

        $usedHandles = [];
        foreach ($fieldLayout->getElementsByType(BaseField::class) as $layoutElement) {
            try {
                $attribute = $layoutElement->attribute();
            } catch (\Throwable) {
                continue;
            }
            if ($attribute !== '') {
                $usedHandles[$attribute] = true;
            }
        }

        $reservedWords = array_unique(array_merge(
            Field::RESERVED_HANDLES,
            $fieldLayout->reservedFieldHandles ?? []
        ));
        $handleValidator = new HandleValidator(['reservedWords' => $reservedWords]);

        $added = [];
        $skipped = [];
        $missing = [];
        $validationErrors = [];
        $elements = $targetTab->getElements();
        $afterWarnings = [];

        foreach ($fieldEntries as $entry) {
            $fieldHandle = $entry['fieldHandle'];
            $overrides = $entry['overrides'] ?? [];
            if (!is_array($overrides)) {
                $overrides = [];
            }
            $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
            if (!$field) {
                $missing[] = $fieldHandle;
                continue;
            }

            if (isset($existingFieldUids[$field->uid]) && !$field::isMultiInstance()) {
                $skipped[] = $fieldHandle;
                continue;
            }

            $elementConfig = [];
            if (array_key_exists('label', $overrides)) {
                $elementConfig['label'] = $overrides['label'];
            }
            if (array_key_exists('handle', $overrides)) {
                $elementConfig['handle'] = $overrides['handle'];
            }
            if (array_key_exists('instructions', $overrides)) {
                $elementConfig['instructions'] = $overrides['instructions'];
            }
            if (array_key_exists('required', $overrides)) {
                $elementConfig['required'] = (bool)$overrides['required'];
            }
            if (array_key_exists('tip', $overrides)) {
                $elementConfig['tip'] = $overrides['tip'];
            }
            if (array_key_exists('warning', $overrides)) {
                $elementConfig['warning'] = $overrides['warning'];
            }
            if (array_key_exists('as', $overrides)) {
                $elementConfig['handle'] = $overrides['as'];
            }
            if (array_key_exists('elementCondition', $overrides)) {
                $elementConfig['elementCondition'] = $overrides['elementCondition'];
            }
            $afterHandle = null;
            $beforeHandle = null;
            if (array_key_exists('after', $overrides)) {
                $afterHandle = $overrides['after'];
            }
            if (array_key_exists('before', $overrides)) {
                $beforeHandle = $overrides['before'];
            }

            if ($afterHandle && $beforeHandle) {
                $validationErrors[] = "Field {$fieldHandle} cannot use both 'after' and 'before' options.";
                continue;
            }

            $effectiveHandle = $elementConfig['handle'] ?? $field->handle;
            if ($effectiveHandle === '' || $effectiveHandle === null) {
                $validationErrors[] = "Field {$fieldHandle} has an empty handle override.";
                continue;
            }

            if (isset($elementConfig['handle'])) {
                $error = null;
                $handleValidator->validate($effectiveHandle, $error);
                if ($error !== null) {
                    $validationErrors[] = "Field {$fieldHandle} has invalid handle '{$effectiveHandle}': {$error}";
                    continue;
                }
            }

            if (isset($usedHandles[$effectiveHandle])) {
                $validationErrors[] = "Field {$fieldHandle} handle '{$effectiveHandle}' is already in use in this layout.";
                continue;
            }

            $layoutElement = new CustomField($field, $elementConfig);
            $layoutElement->setLayout($fieldLayout);
            $insertIndex = null;
            $positionHandle = $afterHandle ?: $beforeHandle;
            $insertAfter = (bool)$afterHandle;

            if ($positionHandle) {
                foreach ($elements as $index => $element) {
                    if ($element instanceof BaseField) {
                        try {
                            if ($element->attribute() === $positionHandle) {
                                $insertIndex = $index;
                                break;
                            }
                        } catch (\Throwable) {
                            continue;
                        }
                    }
                }
            }

            if ($insertIndex === null) {
                $elements[] = $layoutElement;
                if ($positionHandle) {
                    $afterWarnings[] = "Position handle not found in tab; appended to end: {$positionHandle}";
                }
            } else {
                if ($insertAfter) {
                    $insertIndex++;
                }
                array_splice($elements, $insertIndex, 0, [$layoutElement]);
            }
            $added[] = $fieldHandle;
            $existingFieldUids[$field->uid] = true;
            $usedHandles[$effectiveHandle] = true;
        }

        if (!empty($validationErrors)) {
            $this->stdout("Handle validation errors:\n");
            foreach ($validationErrors as $error) {
                $this->stdout("  - {$error}\n");
            }
            $this->stdout("No changes were saved.\n");
            return ExitCode::DATAERR;
        }

        if (!empty($added)) {
            $targetTab->setElements($elements);
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Tab: {$targetTab->name}\n");
        foreach (array_unique($afterWarnings) as $warning) {
            $this->stdout("{$warning}\n");
        }
        $this->stdout("Added: " . (empty($added) ? '(none)' : implode(', ', $added)) . "\n");
        $this->stdout("Skipped (already present): " . (empty($skipped) ? '(none)' : implode(', ', $skipped)) . "\n");
        $this->stdout("Missing fields: " . (empty($missing) ? '(none)' : implode(', ', $missing)) . "\n");

        return ExitCode::OK;
    }

    /**
     * Add a new tab to a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/add-tab myEntryTypeHandle "Tab Name"
     */
    public function actionAddTab(string $handle, string $name): int
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
        $tabs = $fieldLayout->getTabs();

        foreach ($tabs as $tab) {
            if ($tab->name === $name) {
                $this->stdout("Tab already exists: {$name}\n");
                return ExitCode::OK;
            }
        }

        $tabs[] = new FieldLayoutTab(['name' => $name]);
        $fieldLayout->setTabs($tabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Added tab: {$name}\n");

        return ExitCode::OK;
    }

    /**
     * Remove a tab from a specific entry type handle.
     * Fails if the tab still contains fields or other elements.
     *
     * Usage: php craft command-line/entry-types/remove-tab myEntryTypeHandle "Tab Name"
     */
    public function actionRemoveTab(string $handle, string $name): int
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();
        $targetTabIndex = null;

        foreach ($tabs as $index => $tab) {
            if ($tab->name === $name) {
                $targetTabIndex = $index;
                break;
            }
        }

        if ($targetTabIndex === null) {
            $this->stdout("Tab not found: {$name}\n");
            return ExitCode::OK;
        }

        $targetTab = $tabs[$targetTabIndex];
        $elements = $targetTab->getElements();

        if (!empty($elements)) {
            $this->stdout("Tab \"{$name}\" still contains " . count($elements) . " element(s). Remove them first.\n");
            return ExitCode::DATAERR;
        }

        array_splice($tabs, $targetTabIndex, 1);
        $fieldLayout->setTabs($tabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Removed tab: {$name}\n");

        return ExitCode::OK;
    }

    /**
     * Rename a tab on a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/edit-tab myEntryTypeHandle "Old Name" --name="New Name"
     */
    public function actionEditTab(string $handle, string $tabName): int
    {
        if (!$this->name) {
            $this->stdout("Missing --name option.\n");
            return ExitCode::USAGE;
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();
        $targetTab = null;

        foreach ($tabs as $tab) {
            if ($tab->name === $tabName) {
                $targetTab = $tab;
                break;
            }
        }

        if ($targetTab === null) {
            $this->stdout("Tab not found: {$tabName}\n");
            return ExitCode::OK;
        }

        // Check the new name doesn't conflict
        foreach ($tabs as $tab) {
            if ($tab->name === $this->name && $tab !== $targetTab) {
                $this->stdout("A tab named \"{$this->name}\" already exists.\n");
                return ExitCode::DATAERR;
            }
        }

        $oldName = $targetTab->name;
        $targetTab->name = $this->name;
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Renamed tab: \"{$oldName}\" → \"{$this->name}\"\n");

        return ExitCode::OK;
    }

    /**
     * Add UI elements (heading, horizontal rule) to a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/add-ui-elements myEntryTypeHandle --elements-config='[{"type":"heading","heading":"Page Settings"},{"type":"hr"}]'
     * Optional: --tab="Tab Name" (defaults to "Content")
     *
     * Supported element types:
     * - heading: Adds a heading element. Config: {"type":"heading","heading":"Text here","after":"fieldHandle"}
     * - hr: Adds a horizontal rule. Config: {"type":"hr","after":"fieldHandle"}
     */
    public function actionAddUiElements(string $handle): int
    {
        if (!$this->elementsConfig) {
            $this->stdout("Missing --elements-config option.\n");
            return ExitCode::USAGE;
        }

        $decoded = json_decode($this->elementsConfig, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->stdout("Invalid JSON for --elements-config: " . json_last_error_msg() . "\n");
            return ExitCode::DATAERR;
        }
        if (!is_array($decoded)) {
            $this->stdout("--elements-config must decode to a list of objects.\n");
            return ExitCode::DATAERR;
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
        $tabs = $fieldLayout->getTabs();
        $tabName = $this->tab ?: 'Content';
        $targetTabIndex = 0;

        if (empty($tabs)) {
            $tabs = [new FieldLayoutTab(['name' => $tabName])];
            $targetTabIndex = 0;
        } elseif ($this->tab) {
            $targetTabIndex = null;
            foreach ($tabs as $index => $tab) {
                if ($tab->name === $this->tab) {
                    $targetTabIndex = $index;
                    break;
                }
            }

            if ($targetTabIndex === null) {
                $this->stdout("Tab not found: {$this->tab}\n");
                return ExitCode::OK;
            }
        }

        $fieldLayout->setTabs($tabs);
        $tabs = $fieldLayout->getTabs();
        $targetTab = $tabs[$targetTabIndex] ?? $tabs[0];

        $added = [];
        $elements = $targetTab->getElements();
        $lastInsertIndex = null;

        foreach ($decoded as $index => $config) {
            if (!is_array($config) || empty($config['type'])) {
                $this->stdout("--elements-config entry {$index} must include a type.\n");
                return ExitCode::DATAERR;
            }

            $type = $config['type'];
            $afterHandle = $config['after'] ?? null;
            $beforeHandle = $config['before'] ?? null;
            $layoutElement = null;

            if ($afterHandle && $beforeHandle) {
                $this->stdout("Error: Cannot use both 'after' and 'before' for element {$index}.\n");
                return ExitCode::DATAERR;
            }

            switch ($type) {
                case 'heading':
                    $headingText = $config['heading'] ?? '';
                    $layoutElement = new \craft\fieldlayoutelements\Heading([
                        'heading' => $headingText,
                    ]);
                    $added[] = "Heading: \"{$headingText}\"";
                    break;

                case 'hr':
                case 'horizontal-rule':
                    $layoutElement = new \craft\fieldlayoutelements\HorizontalRule();
                    $added[] = "Horizontal rule";
                    break;

                case 'tip':
                    $tipText = $config['tip'] ?? '';
                    $style = $config['style'] ?? 'tip'; // 'tip' or 'warning'
                    $dismissible = $config['dismissible'] ?? false;
                    $layoutElement = new \craft\fieldlayoutelements\Tip([
                        'tip' => $tipText,
                        'style' => $style,
                        'dismissible' => (bool)$dismissible,
                    ]);
                    $added[] = "Tip ({$style}): \"{$tipText}\"";
                    break;

                case 'markdown':
                    $content = $config['content'] ?? '';
                    $layoutElement = new \craft\fieldlayoutelements\Markdown([
                        'content' => $content,
                    ]);
                    $preview = strlen($content) > 30 ? substr($content, 0, 30) . '...' : $content;
                    $added[] = "Markdown: \"{$preview}\"";
                    break;

                case 'line-break':
                case 'br':
                    $layoutElement = new \craft\fieldlayoutelements\LineBreak();
                    $added[] = "Line break";
                    break;

                default:
                    $this->stdout("Unknown element type: {$type}\n");
                    return ExitCode::DATAERR;
            }

            if ($layoutElement) {
                $insertIndex = null;
                $positionHandle = $afterHandle ?: $beforeHandle;
                $insertAfter = (bool)$afterHandle;

                if ($positionHandle) {
                    foreach ($elements as $idx => $element) {
                        if ($element instanceof BaseField) {
                            try {
                                if ($element->attribute() === $positionHandle) {
                                    $insertIndex = $insertAfter ? $idx + 1 : $idx;
                                    break;
                                }
                            } catch (\Throwable) {
                                continue;
                            }
                        }
                    }
                    if ($insertIndex !== null) {
                        $lastInsertIndex = $insertIndex;
                    }
                } elseif ($lastInsertIndex !== null) {
                    // No position specified, insert after the last inserted element
                    $insertIndex = $lastInsertIndex;
                }

                if ($insertIndex === null) {
                    $elements[] = $layoutElement;
                    $lastInsertIndex = count($elements) - 1;
                    if ($positionHandle) {
                        $this->stdout("Warning: Position handle '{$positionHandle}' not found; appended to end.\n");
                    }
                } else {
                    array_splice($elements, $insertIndex, 0, [$layoutElement]);
                    $lastInsertIndex = $insertIndex + 1;
                }
            }
        }

        if (!empty($added)) {
            $targetTab->setElements($elements);
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Tab: {$targetTab->name}\n");
        $this->stdout("Added: " . (empty($added) ? '(none)' : implode(', ', $added)) . "\n");

        return ExitCode::OK;
    }

    /**
     * Remove UI elements (heading, horizontal rule) from a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/remove-ui-elements myEntryTypeHandle --elements-config='[{"type":"heading","heading":"Page Settings"},{"type":"hr"}]'
     * Optional: --tab="Tab Name" (defaults to searching all tabs)
     */
    public function actionRemoveUiElements(string $handle): int
    {
        if (!$this->elementsConfig) {
            $this->stdout("Missing --elements-config option.\n");
            return ExitCode::USAGE;
        }

        $decoded = json_decode($this->elementsConfig, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->stdout("Invalid JSON for --elements-config: " . json_last_error_msg() . "\n");
            return ExitCode::DATAERR;
        }
        if (!is_array($decoded)) {
            $this->stdout("--elements-config must decode to a list of objects.\n");
            return ExitCode::DATAERR;
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();
        if (empty($tabs)) {
            $this->stdout("Entry type {$handle} has no field layout tabs.\n");
            return ExitCode::OK;
        }

        if ($this->tab) {
            $tabs = array_values(array_filter($tabs, fn($tab) => $tab->name === $this->tab));
            if (empty($tabs)) {
                $this->stdout("Tab not found: {$this->tab}\n");
                return ExitCode::OK;
            }
        }

        $removed = [];
        foreach ($tabs as $tab) {
            $elements = $tab->getElements();
            $newElements = [];

            foreach ($elements as $element) {
                $shouldRemove = false;

                foreach ($decoded as $config) {
                    $type = $config['type'] ?? null;

                    if ($type === 'heading' && $element instanceof \craft\fieldlayoutelements\Heading) {
                        if (isset($config['heading'])) {
                            if ($element->heading === $config['heading']) {
                                $shouldRemove = true;
                                $removed[] = "Heading: \"{$element->heading}\"";
                                break;
                            }
                        } else {
                            $shouldRemove = true;
                            $removed[] = "Heading: \"{$element->heading}\"";
                            break;
                        }
                    }

                    if (($type === 'hr' || $type === 'horizontal-rule') && $element instanceof \craft\fieldlayoutelements\HorizontalRule) {
                        $shouldRemove = true;
                        $removed[] = "Horizontal rule";
                        break;
                    }

                    if ($type === 'tip' && $element instanceof \craft\fieldlayoutelements\Tip) {
                        if (isset($config['tip'])) {
                            if ($element->tip === $config['tip']) {
                                $shouldRemove = true;
                                $removed[] = "Tip: \"{$element->tip}\"";
                                break;
                            }
                        } else {
                            $shouldRemove = true;
                            $removed[] = "Tip: \"{$element->tip}\"";
                            break;
                        }
                    }

                    if ($type === 'markdown' && $element instanceof \craft\fieldlayoutelements\Markdown) {
                        if (isset($config['content'])) {
                            if ($element->content === $config['content']) {
                                $shouldRemove = true;
                                $removed[] = "Markdown";
                                break;
                            }
                        } else {
                            $shouldRemove = true;
                            $removed[] = "Markdown";
                            break;
                        }
                    }

                    if (($type === 'line-break' || $type === 'br') && $element instanceof \craft\fieldlayoutelements\LineBreak) {
                        $shouldRemove = true;
                        $removed[] = "Line break";
                        break;
                    }
                }

                if (!$shouldRemove) {
                    $newElements[] = $element;
                }
            }

            $tab->setElements($newElements);
        }

        if (empty($removed)) {
            $this->stdout("No matching UI elements found to remove.\n");
            return ExitCode::OK;
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Removed: " . implode(', ', $removed) . "\n");

        return ExitCode::OK;
    }

    /**
     * Remove fields from a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/remove-fields myEntryTypeHandle --fields-config='[{"handle":"text"},{"handle":"text","as":"introText"}]'
     */
    public function actionRemoveFields(string $handle): int
    {
        if (!$this->fieldsConfig) {
            $this->stdout("Missing --fields-config option.\n");
            return ExitCode::USAGE;
        }

        $decoded = json_decode($this->fieldsConfig, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->stdout("Invalid JSON for --fields-config: " . json_last_error_msg() . "\n");
            return ExitCode::DATAERR;
        }
        if (!is_array($decoded)) {
            $this->stdout("--fields-config must decode to a list of objects.\n");
            return ExitCode::DATAERR;
        }

        $entries = [];
        foreach ($decoded as $index => $entry) {
            if (!is_array($entry) || empty($entry['handle']) || !is_string($entry['handle'])) {
                $this->stdout("--fields-config entry {$index} must include a string handle.\n");
                return ExitCode::DATAERR;
            }
            $entries[] = [
                'fieldHandle' => $entry['handle'],
                'instanceHandle' => isset($entry['as']) && is_string($entry['as']) ? $entry['as'] : null,
            ];
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();
        if (empty($tabs)) {
            $this->stdout("Entry type {$handle} has no field layout tabs.\n");
            return ExitCode::OK;
        }

        if ($this->tab) {
            $tabs = array_values(array_filter($tabs, fn($tab) => $tab->name === $this->tab));
            if (empty($tabs)) {
                $this->stdout("Tab not found: {$this->tab}\n");
                return ExitCode::OK;
            }
        }

        $removed = [];
        foreach ($tabs as $tab) {
            $elements = $tab->getElements();
            $newElements = [];

            foreach ($elements as $element) {
                if (!$element instanceof CustomField) {
                    $newElements[] = $element;
                    continue;
                }

                $match = false;
                foreach ($entries as $entry) {
                    try {
                        $field = $element->getField();
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($field->handle !== $entry['fieldHandle']) {
                        continue;
                    }

                    if ($entry['instanceHandle'] !== null) {
                        try {
                            if ($element->attribute() !== $entry['instanceHandle']) {
                                continue;
                            }
                        } catch (\Throwable) {
                            continue;
                        }
                    }

                    $match = true;
                    $removed[] = $entry['instanceHandle'] ? "{$entry['fieldHandle']} (as {$entry['instanceHandle']})" : $entry['fieldHandle'];
                    break;
                }

                if (!$match) {
                    $newElements[] = $element;
                }
            }

            $tab->setElements($newElements);
        }

        if (empty($removed)) {
            $this->stdout("No matching fields found to remove.\n");
            return ExitCode::OK;
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        if ($this->tab) {
            $this->stdout("Tab: {$this->tab}\n");
        }
        $this->stdout("Removed: " . implode(', ', $removed) . "\n");

        return ExitCode::OK;
    }

    /**
     * Edit fields on a specific entry type handle.
     *
     * Usage: php craft command-line/entry-types/edit-fields myEntryTypeHandle --fields-config='[{"handle":"metaTitle","label":"New Label","required":true}]'
     * Optional: --tab="Tab Name" (defaults to searching all tabs)
     */
    public function actionEditFields(string $handle): int
    {
        if (!$this->fieldsConfig) {
            $this->stdout("Missing --fields-config option.\n");
            return ExitCode::USAGE;
        }

        $decoded = json_decode($this->fieldsConfig, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->stdout("Invalid JSON for --fields-config: " . json_last_error_msg() . "\n");
            return ExitCode::DATAERR;
        }
        if (!is_array($decoded)) {
            $this->stdout("--fields-config must decode to a list of objects.\n");
            return ExitCode::DATAERR;
        }

        $editEntries = [];
        foreach ($decoded as $index => $entry) {
            if (!is_array($entry) || empty($entry['handle']) || !is_string($entry['handle'])) {
                $this->stdout("--fields-config entry {$index} must include a string handle to identify the field.\n");
                return ExitCode::DATAERR;
            }
            $editEntries[] = $entry;
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $this->stdout("Entry type {$handle} has no field layout.\n");
            return ExitCode::OK;
        }

        $tabs = $fieldLayout->getTabs();
        if (empty($tabs)) {
            $this->stdout("Entry type {$handle} has no field layout tabs.\n");
            return ExitCode::OK;
        }

        $searchTabs = $tabs;
        if ($this->tab) {
            $searchTabs = array_values(array_filter($tabs, fn($tab) => $tab->name === $this->tab));
            if (empty($searchTabs)) {
                $this->stdout("Tab not found: {$this->tab}\n");
                return ExitCode::OK;
            }
        }

        // Collect all used handles across all tabs (for rename validation)
        $usedHandles = [];
        foreach ($tabs as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof BaseField) {
                    try {
                        $attr = $element->attribute();
                        if ($attr !== '') {
                            $usedHandles[$attr] = true;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        }

        $reservedWords = array_unique(array_merge(
            Field::RESERVED_HANDLES,
            $fieldLayout->reservedFieldHandles ?? []
        ));
        $handleValidator = new HandleValidator(['reservedWords' => $reservedWords]);

        $edited = [];
        $notFound = [];
        $validationErrors = [];

        foreach ($editEntries as $entry) {
            $targetHandle = $entry['handle'];
            $found = false;

            foreach ($searchTabs as $tab) {
                $elements = $tab->getElements();
                $elementIndex = null;
                $matchedElement = null;

                foreach ($elements as $idx => $element) {
                    if (!$element instanceof CustomField) {
                        continue;
                    }
                    try {
                        if ($element->attribute() === $targetHandle) {
                            $elementIndex = $idx;
                            $matchedElement = $element;
                            break;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                }

                if ($matchedElement === null) {
                    continue;
                }

                $found = true;
                $changes = [];

                // Apply property updates
                if (array_key_exists('label', $entry)) {
                    $matchedElement->label = $entry['label'];
                    $changes[] = 'label';
                }
                if (array_key_exists('instructions', $entry)) {
                    $matchedElement->instructions = $entry['instructions'];
                    $changes[] = 'instructions';
                }
                if (array_key_exists('required', $entry)) {
                    $matchedElement->required = (bool)$entry['required'];
                    $changes[] = 'required';
                }
                if (array_key_exists('tip', $entry)) {
                    $matchedElement->tip = $entry['tip'];
                    $changes[] = 'tip';
                }
                if (array_key_exists('warning', $entry)) {
                    $matchedElement->warning = $entry['warning'];
                    $changes[] = 'warning';
                }
                if (array_key_exists('elementCondition', $entry)) {
                    $matchedElement->setElementCondition($entry['elementCondition']);
                    $changes[] = 'elementCondition';
                }

                // Handle rename (changing the instance handle)
                if (array_key_exists('as', $entry)) {
                    $newHandle = $entry['as'];
                    $error = null;
                    $handleValidator->validate($newHandle, $error);
                    if ($error !== null) {
                        $validationErrors[] = "Field {$targetHandle} rename to '{$newHandle}' invalid: {$error}";
                        break;
                    }
                    if (isset($usedHandles[$newHandle]) && $newHandle !== $targetHandle) {
                        $validationErrors[] = "Field {$targetHandle} rename to '{$newHandle}' conflicts with existing handle.";
                        break;
                    }
                    $matchedElement->handle = $newHandle;
                    unset($usedHandles[$targetHandle]);
                    $usedHandles[$newHandle] = true;
                    $changes[] = "handle → {$newHandle}";
                }

                // Handle tab movement
                $targetTabName = $entry['tab'] ?? null;
                if ($targetTabName) {
                    $destinationTab = null;
                    foreach ($tabs as $t) {
                        if ($t->name === $targetTabName) {
                            $destinationTab = $t;
                            break;
                        }
                    }

                    if ($destinationTab === null) {
                        $validationErrors[] = "Field {$targetHandle} target tab '{$targetTabName}' not found.";
                        break;
                    }

                    if ($destinationTab !== $tab) {
                        // Remove from source tab
                        array_splice($elements, $elementIndex, 1);
                        $tab->setElements($elements);

                        // Add to destination tab
                        $destElements = $destinationTab->getElements();
                        $destElements[] = $matchedElement;
                        $destinationTab->setElements($destElements);

                        $changes[] = "tab → {$targetTabName}";

                        // Repositioning within destination tab handled below
                        $elements = $destinationTab->getElements();
                        $elementIndex = count($elements) - 1;
                        $tab = $destinationTab;
                    }
                }

                // Handle repositioning
                $afterHandle = $entry['after'] ?? null;
                $beforeHandle = $entry['before'] ?? null;

                if ($afterHandle && $beforeHandle) {
                    $validationErrors[] = "Field {$targetHandle} cannot use both 'after' and 'before'.";
                    break;
                }

                if ($afterHandle || $beforeHandle) {
                    $positionHandle = $afterHandle ?: $beforeHandle;
                    $insertAfter = (bool)$afterHandle;
                    $positionIndex = null;

                    // Remove from current position
                    array_splice($elements, $elementIndex, 1);

                    foreach ($elements as $idx => $el) {
                        if ($el instanceof BaseField) {
                            try {
                                if ($el->attribute() === $positionHandle) {
                                    $positionIndex = $idx;
                                    break;
                                }
                            } catch (\Throwable) {
                                continue;
                            }
                        }
                    }

                    if ($positionIndex === null) {
                        // Position handle not found, put it back at end
                        $elements[] = $matchedElement;
                        $this->stdout("Warning: Position handle '{$positionHandle}' not found for {$targetHandle}; moved to end.\n");
                    } else {
                        if ($insertAfter) {
                            $positionIndex++;
                        }
                        array_splice($elements, $positionIndex, 0, [$matchedElement]);
                    }

                    $tab->setElements($elements);
                    $changes[] = ($insertAfter ? "after" : "before") . " {$positionHandle}";
                }

                $edited[] = $targetHandle . ' (' . implode(', ', $changes) . ')';
                break;
            }

            if (!$found) {
                $notFound[] = $targetHandle;
            }
        }

        if (!empty($validationErrors)) {
            $this->stdout("Validation errors:\n");
            foreach ($validationErrors as $error) {
                $this->stdout("  - {$error}\n");
            }
            $this->stdout("No changes were saved.\n");
            return ExitCode::DATAERR;
        }

        if (empty($edited)) {
            $this->stdout("No matching fields found to edit.\n");
            if (!empty($notFound)) {
                $this->stdout("Not found: " . implode(', ', $notFound) . "\n");
            }
            return ExitCode::OK;
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $this->stdout("Failed to save entry type {$handle}.\n");
            if ($entryType->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entryType->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry type: {$entryType->name} ({$entryType->handle})\n");
        $this->stdout("Edited: " . implode(', ', $edited) . "\n");
        if (!empty($notFound)) {
            $this->stdout("Not found: " . implode(', ', $notFound) . "\n");
        }

        return ExitCode::OK;
    }

    private function outputCustomField(CustomField $element): void
    {
        $field = $element->getField();
        $layoutHandle = null;

        try {
            $layoutHandle = $element->attribute();
        } catch (\Throwable) {
            $layoutHandle = null;
        }

        $handle = $layoutHandle ?: $field->handle;
        $originalHandle = $element->getOriginalHandle();

        $this->stdout("Handle: {$handle}\n");

        if ($originalHandle && $originalHandle !== $handle) {
            $this->stdout("  Field handle: {$originalHandle}\n");
        }

        $this->outputFieldDetails($field);
    }

    private function outputField(FieldInterface $field): void
    {
        $this->stdout("Handle: {$field->handle}\n");
        $this->outputFieldDetails($field);
    }

    private function outputFieldDetails(FieldInterface $field): void
    {
        $this->stdout("  Name: {$field->name}\n");
        $this->stdout("  Type: " . get_class($field) . "\n");
        $this->stdout("  ID: {$field->id}\n");
        $this->stdout("  Required: " . ($field->required ? 'yes' : 'no') . "\n");
        $this->stdout("  Instructions: " . ($field->instructions ?: '(none)') . "\n");

        if ($field->translationMethod && $field->translationMethod !== 'none') {
            $this->stdout("  Translation method: {$field->translationMethod}\n");
        }

        if ($field->translationKeyFormat) {
            $this->stdout("  Translation key format: {$field->translationKeyFormat}\n");
        }

        $settings = method_exists($field, 'getSettings') ? $field->getSettings() : $field->settings;
        $settingsJson = json_encode(
            $settings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($settingsJson === false || $settingsJson === null) {
            $settingsJson = '{}';
        }

        $settingsJson = preg_replace('/^/m', '    ', $settingsJson);
        $this->stdout("  Settings:\n{$settingsJson}\n");
    }

    private function outputNativeField(\craft\fieldlayoutelements\BaseNativeField $field): void
    {
        $label = method_exists($field, 'label') ? $field->label() : $field->label;
        $attribute = method_exists($field, 'attribute') ? $field->attribute() : '(unknown)';

        $this->stdout("Native field: {$label}\n");
        $this->stdout("  Attribute: {$attribute}\n");
        $this->stdout("  Type: " . get_class($field) . "\n");
        $this->stdout("  Required: " . ($field->required ? 'yes' : 'no') . "\n");
        $this->stdout("  Mandatory: " . ($field->mandatory ? 'yes' : 'no') . "\n");
        $this->stdout("  Requirable: " . ($field->requirable ? 'yes' : 'no') . "\n");

        if ($field->instructions) {
            $this->stdout("  Instructions: {$field->instructions}\n");
        }

        if ($field->tip) {
            $this->stdout("  Tip: {$field->tip}\n");
        }

        if ($field->warning) {
            $this->stdout("  Warning: {$field->warning}\n");
        }
    }
}
