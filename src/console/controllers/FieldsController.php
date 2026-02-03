<?php

namespace whereverly\craftcommandline\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

class FieldsController extends Controller
{
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
