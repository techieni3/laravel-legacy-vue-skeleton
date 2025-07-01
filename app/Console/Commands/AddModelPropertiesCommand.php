<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ReflectionClass;

class AddModelPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:properties {model? : The name of the model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add properties to model class based on migration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelName = $this->argument('model');

        if ( ! $modelName) {
            $models = $this->getAllModels();

            if ($models === []) {
                $this->error('No models found.');

                return 1;
            }

            // Prompt user to choose from the list
            /** @var string $modelName */
            $modelName = $this->choice('Select a model:', $models);
        }

        // Get the fully qualified model class name
        $modelClass = $this->getModelClass($modelName);

        if ($modelClass === null || $modelClass === '' || $modelClass === '0') {
            $this->error("Model {$modelName} not found.");

            return 1;
        }

        $this->info("Processing model: {$modelClass}");

        // Get the table name from the model
        $tableName = $this->getTableName($modelClass);

        if ($tableName === null || $tableName === '' || $tableName === '0') {
            $this->error("Could not determine table name for model {$modelName}.");

            return 1;
        }

        // Find migration files for the table
        $migrationFiles = $this->findMigrationFiles($tableName);

        if ($migrationFiles === []) {
            $this->error("No migration files found for table {$tableName}.");

            return 1;
        }

        $this->info('Found ' . count($migrationFiles) . ' migration file(s).');

        // Parse migration files to extract column definitions
        $columns = $this->parseColumnsFromMigrations($migrationFiles);

        if ($columns === []) {
            $this->error('No columns found in migration files.');

            return 1;
        }

        // Generate property docblocks
        $docblocks = $this->generatePropertyDocblocks($columns);

        // Update the model file
        $this->updateModelFile($modelClass, $docblocks);

        $this->info("Model {$modelName} updated successfully.");

        return 0;
    }

    /**
     * @return list<string>
     */
    protected function getAllModels(): array
    {
        $modelPath = app_path('Models');
        $models = [];

        if ( ! is_dir($modelPath)) {
            return $models;
        }

        foreach (scandir($modelPath) as $file) {
            if (Str::endsWith($file, '.php')) {
                $models[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return $models;
    }

    /**
     * Get the fully qualified model class name.
     */
    protected function getModelClass(string $modelName): ?string
    {
        // Check if the model name already includes the namespace
        if (class_exists($modelName)) {
            return $modelName;
        }

        // Try with App\Models namespace
        $modelClass = "App\\Models\\{$modelName}";
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        // Try with App namespace
        $modelClass = "App\\{$modelName}";
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        return null;
    }

    /**
     * Get the table name from the model.
     */
    protected function getTableName(string $modelClass): ?string
    {
        try {
            /** @var Model $model */
            $model = new $modelClass;

            return $model->getTable();
        } catch (Exception $exception) {
            $this->error('Error getting table name: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Find migration files for the table.
     *
     * @return list<string>
     */
    protected function findMigrationFiles(string $tableName): array
    {
        $migrationPath = database_path('migrations');

        /** @var list<string> $files */
        $files = File::glob($migrationPath . '/*.php');

        $matchingFiles = [];

        foreach ($files as $file) {
            $content = File::get($file);

            // Check for table creation
            if (preg_match("/Schema::create\(['\"]" . $tableName . "['\"]/", $content) ||
                preg_match("/Schema::table\(['\"]" . $tableName . "['\"]/", $content)) {
                $matchingFiles[] = $file;
            }
        }

        return $matchingFiles;
    }

    /**
     * Parse migration files to extract column definitions.
     *
     * @param  list<string>  $migrationFiles
     * @return array<string, string>
     */
    protected function parseColumnsFromMigrations(array $migrationFiles): array
    {
        $columns = [];

        foreach ($migrationFiles as $file) {
            $content = File::get($file);

            // Extract the closure content inside Schema::create or Schema::table
            if (preg_match('/Schema::(create|table)\([^,]+,\s*(?:static\s*)?function\s*\(\s*Blueprint\s*\$table\s*\)\s*(?:use\s*\([^)]*\))?\s*:?\s*(?:void)?\s*{(.+?)}\s*\)\s*;/s', $content, $matches)) {
                $schemaContent = $matches[2];

                // Extract column definitions
                $this->extractColumnDefinitions($schemaContent, $columns);
            }
        }

        return $columns;
    }

    /**
     * Extract column definitions from schema content.
     *
     * @param  array<string, string>  $columns
     */
    protected function extractColumnDefinitions(string $schemaContent, array &$columns): void
    {
        // Common column types
        $columnTypes = [
            'bigIncrements' => 'int',
            'bigInteger' => 'int',
            'binary' => 'string',
            'boolean' => 'bool',
            'char' => 'string',
            'dateTime' => '\Illuminate\Support\Carbon',
            'dateTimeTz' => '\Illuminate\Support\Carbon',
            'date' => '\Illuminate\Support\Carbon',
            'decimal' => 'float',
            'double' => 'float',
            'enum' => 'string',
            'float' => 'float',
            'foreignId' => 'int',
            'increments' => 'int',
            'integer' => 'int',
            'json' => 'array',
            'jsonb' => 'array',
            'longText' => 'string',
            'mediumInteger' => 'int',
            'mediumText' => 'string',
            'morphs' => 'string',
            'nullableMorphs' => 'string|null',
            'nullableTimestamps' => '\Illuminate\Support\Carbon|null',
            'smallInteger' => 'int',
            'smallIncrements' => 'int',
            'string' => 'string',
            'text' => 'string',
            'time' => 'string',
            'timeTz' => 'string',
            'timestamp' => '\Illuminate\Support\Carbon',
            'timestampTz' => '\Illuminate\Support\Carbon',
            'tinyInteger' => 'int',
            'tinyIncrements' => 'int',
            'unsignedBigInteger' => 'int',
            'unsignedDecimal' => 'float',
            'unsignedInteger' => 'int',
            'unsignedMediumInteger' => 'int',
            'unsignedSmallInteger' => 'int',
            'unsignedTinyInteger' => 'int',
            'uuid' => 'string',
            'year' => 'int',
        ];

        // Special cases
        $specialColumns = [
            'id' => ['name' => 'id', 'type' => 'int'],
            'rememberToken' => ['name' => 'remember_token', 'type' => 'string|null'],
            'softDeletes' => ['name' => 'deleted_at', 'type' => '\Illuminate\Support\Carbon|null'],
            'softDeletesTz' => ['name' => 'deleted_at', 'type' => '\Illuminate\Support\Carbon|null'],
            'timestamps' => [
                ['name' => 'created_at', 'type' => '\Illuminate\Support\Carbon|null'],
                ['name' => 'updated_at', 'type' => '\Illuminate\Support\Carbon|null'],
            ],
        ];

        // Match all $table->method(...) lines
        preg_match_all('/\$table->(\w+)\((.*?)\);/', $schemaContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $method = $match[1];
            $args = $match[2];

            // Handle special columns
            if (isset($specialColumns[$method])) {
                $columnInfo = $specialColumns[$method];
                if (isset($columnInfo[0])) {
                    // timestamps, etc.
                    foreach ($columnInfo as $info) {
                        $columns[$info['name']] = $info['type'];
                    }
                } elseif (isset($columnInfo['name'], $columnInfo['type'])) {
                    $columns[$columnInfo['name']] = $columnInfo['type'];
                }

                continue;
            }

            // Handle regular column types
            // Extract column name (first argument)
            if (isset($columnTypes[$method]) && preg_match('/[\'"]([^\'"]+)[\'"]/', $args, $argMatch)) {
                $columnName = $argMatch[1];
                $type = $columnTypes[$method];
                // Check if nullable is chained in method call
                if (str_contains($match[0], '->nullable')) {
                    $type .= '|null';
                }

                $columns[$columnName] = $type;
            }
        }
    }

    /**
     * Generate property docblocks for the model.
     *
     * @param  array<string, string>  $columns
     */
    protected function generatePropertyDocblocks(array $columns): string
    {
        $docblocks = "/**\n";

        // Ensure id is the first property
        if (isset($columns['id'])) {
            $docblocks .= " * @property int \$id\n";
            unset($columns['id']);
        }

        // Add remaining properties
        foreach ($columns as $column => $type) {
            $docblocks .= " * @property {$type} \${$column}\n";
        }

        return $docblocks . ' */';
    }

    /**
     * Update the model file with the generated docblocks.
     */
    protected function updateModelFile(string $modelClass, string $docblocks): void
    {
        try {
            if ( ! class_exists($modelClass)) {
                $this->error("Model class {$modelClass} does not exist.");

                return;
            }

            $reflection = new ReflectionClass($modelClass);
            $filePath = $reflection->getFileName();

            if ( ! $filePath || ! File::exists($filePath)) {
                $this->error("Could not find file for model {$modelClass}.");

                return;
            }

            $content = File::get($filePath);

            // Check if the model already has property docblocks
            // Match both old format (with "Properties generated from database schema") and new format
            $pattern = '/\/\*\*\s*\n(\s*\*\s*Properties generated from database schema\s*\n)?\s*\*\s*@property.*?\*\//s';
            if (preg_match($pattern, $content)) {
                // Replace existing docblocks
                $replaced = preg_replace($pattern, $docblocks, $content);
                if ($replaced === null) {
                    $this->error("Failed to update docblocks for model {$modelClass}.");

                    return;
                }

                $content = $replaced;
            } else {
                // Add new docblocks before the class definition
                $classPattern = '/class\s+' . class_basename($modelClass) . '/';
                if (preg_match($classPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $position = $matches[0][1];
                    $content = substr_replace($content, $docblocks . "\n", $position, 0);
                }
            }

            File::put($filePath, $content);

            // Run Laravel Pint on the file
            $this->runPintOnFile($filePath);
        } catch (Exception $exception) {
            $this->error('Error updating model file: ' . $exception->getMessage());
        }
    }

    /**
     * Run Laravel Pint on the file with custom configuration.
     */
    protected function runPintOnFile(string $filePath): void
    {
        try {
            // Create a temporary Pint configuration file with our custom rules
            $tempConfigPath = base_path() . '/pint.json';

            // Run Pint on the file with our custom config
            $relativePath = str_replace(base_path() . '/', '', $filePath);
            $command = "vendor/bin/pint {$relativePath} --config={$tempConfigPath}";

            $result = Process::run($command);

            if ($result->failed()) {
                $this->warn("Pint formatting failed with code {$result->exitCode()}.");
                $this->warn($result->output());
            }

        } catch (Exception $exception) {
            $this->warn('Error running Pint: ' . $exception->getMessage());
        }
    }
}
