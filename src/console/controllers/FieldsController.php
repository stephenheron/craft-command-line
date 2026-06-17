<?php

namespace whereverly\craftcommandline\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

class FieldsController extends Controller
{
    public ?string $type = null;
    public ?string $name = null;
    public ?string $handle = null;
    public ?string $instructions = null;
    public ?string $settings = null;
    public bool $searchable = false;
    public ?string $translationMethod = null;
    public ?string $translationKeyFormat = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'create') {
            $options = array_merge($options, [
                'type',
                'name',
                'handle',
                'instructions',
                'settings',
                'searchable',
                'translationMethod',
                'translationKeyFormat',
            ]);
        }

        return $options;
    }

    /**
     * Create a new custom field.
     *
     * Usage: php craft command-line/fields/create --type="craft\fields\Entries" --name="Featured Post" --handle="featuredPost" --settings='{"sources":["section:UID"],"maxRelations":1}'
     */
    public function actionCreate(): int
    {
        if (!$this->type) {
            $this->stdout("Missing --type option (e.g. \"craft\\fields\\Entries\").\n");
            return ExitCode::USAGE;
        }

        if (!$this->name) {
            $this->stdout("Missing --name option.\n");
            return ExitCode::USAGE;
        }

        if (!$this->handle) {
            $this->stdout("Missing --handle option.\n");
            return ExitCode::USAGE;
        }

        $fieldsService = Craft::$app->getFields();

        if (!class_exists($this->type)) {
            $this->stdout("Field type class not found: {$this->type}\n");
            return ExitCode::DATAERR;
        }

        if ($fieldsService->getFieldByHandle($this->handle)) {
            $this->stdout("A field already exists with handle: {$this->handle}\n");
            return ExitCode::DATAERR;
        }

        $settings = [];
        if ($this->settings) {
            $settings = json_decode($this->settings, true);
            if ($settings === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->stdout("Invalid JSON for --settings: " . json_last_error_msg() . "\n");
                return ExitCode::DATAERR;
            }
            if (!is_array($settings)) {
                $this->stdout("--settings must decode to an object of setting keys and values.\n");
                return ExitCode::DATAERR;
            }
        }

        $config = [
            'type' => $this->type,
            'name' => $this->name,
            'handle' => $this->handle,
            'instructions' => $this->instructions,
            'searchable' => $this->searchable,
            'settings' => $settings,
        ];

        if ($this->translationMethod) {
            $config['translationMethod'] = $this->translationMethod;
        }

        if ($this->translationKeyFormat) {
            $config['translationKeyFormat'] = $this->translationKeyFormat;
        }

        $field = $fieldsService->createField($config);

        if (!$fieldsService->saveField($field)) {
            $this->stdout("Failed to save field.\n");
            if ($field->hasErrors()) {
                $this->stdout("Errors:\n");
                foreach ($field->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stdout("  {$attribute}: {$error}\n");
                    }
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Field created.\n");
        $this->stdout("Handle: {$field->handle}\n");
        $this->stdout("Name: {$field->name}\n");
        $this->stdout("Type: " . get_class($field) . "\n");
        $this->stdout("ID: {$field->id}\n");

        return ExitCode::OK;
    }

    /**
     * Get all current fields.
     *
     * Usage: php craft command-line/fields/get
     */
    public function actionGet(): int
    {
        $fields = Craft::$app->getFields()->getAllFields();

        if (empty($fields)) {
            $this->stdout("No fields found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($fields) . " field(s):\n\n");

        foreach ($fields as $field) {
            $this->stdout("Handle: {$field->handle}\n");
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
            $this->stdout("\n");
        }

        return ExitCode::OK;
    }
}
