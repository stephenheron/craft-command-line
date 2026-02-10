<?php

namespace whereverly\craftcommandline\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use yii\console\ExitCode;

class EntriesController extends Controller
{
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $site = null;
    public ?string $status = null;
    public ?string $fields = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'create') {
            $options = array_merge($options, ['title', 'slug', 'site', 'status', 'fields']);
        }

        return $options;
    }

    /**
     * Create a new entry for a specific entry type handle.
     *
     * Usage: php craft command-line/entries/create myEntryTypeHandle --title="My Title" --fields='{"fieldHandle":"value"}'
     */
    public function actionCreate(string $handle): int
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            $this->stdout("Entry type not found for handle: {$handle}\n");
            return ExitCode::OK;
        }

        if ($entryType->hasTitleField && !$this->title) {
            $this->stdout("Missing --title option for entry types with a title field.\n");
            return ExitCode::USAGE;
        }

        $site = $this->site
            ? Craft::$app->getSites()->getSiteByHandle($this->site)
            : Craft::$app->getSites()->getPrimarySite();

        if (!$site) {
            $this->stdout("Site not found for handle: {$this->site}\n");
            return ExitCode::DATAERR;
        }

        $enabled = true;
        if ($this->status) {
            $status = strtolower($this->status);
            if ($status === 'enabled') {
                $enabled = true;
            } elseif ($status === 'disabled') {
                $enabled = false;
            } else {
                $this->stdout("Invalid --status value. Use 'enabled' or 'disabled'.\n");
                return ExitCode::USAGE;
            }
        }

        $fieldValues = [];
        if ($this->fields) {
            $fieldValues = json_decode($this->fields, true);
            if ($fieldValues === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->stdout("Invalid JSON for --fields: " . json_last_error_msg() . "\n");
                return ExitCode::DATAERR;
            }
            if (!is_array($fieldValues)) {
                $this->stdout("--fields must decode to an object of field handles and values.\n");
                return ExitCode::DATAERR;
            }
        }

        $entry = new Entry();
        $entry->sectionId = $entryType->sectionId;
        $entry->typeId = $entryType->id;
        $entry->siteId = $site->id;
        $entry->enabled = $enabled;

        if ($this->title) {
            $entry->title = $this->title;
        }

        if ($this->slug) {
            $entry->slug = $this->slug;
        }

        if (!empty($fieldValues)) {
            $entry->setFieldValues($fieldValues);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            $this->stdout("Failed to save entry.\n");
            if ($entry->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($entry->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry created.\n");
        $this->stdout("ID: {$entry->id}\n");
        $this->stdout("Title: {$entry->title}\n");
        $this->stdout("Slug: {$entry->slug}\n");
        $this->stdout("Site: {$site->handle}\n");
        $this->stdout("Status: " . ($entry->enabled ? 'enabled' : 'disabled') . "\n");

        return ExitCode::OK;
    }
}
