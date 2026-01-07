<?php

namespace Iquesters\Integration\Database\Seeders;

use Iquesters\Integration\Models\SupportedIntegration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IntegrationSeeder extends Seeder
{
    private $stats = [
        'total_processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'metadata_processed' => 0,
        'metadata_created' => 0,
        'metadata_skipped' => 0,
    ];

    private $currentFile = '';
    private $processedRecords = [];

    public function run()
    {
        $this->command->info('Starting Integration seeding...');
        $this->command->newLine();

        $this->seedFromDirectory(__DIR__);

        $this->displayFinalStats();
    }

    protected function seedFromDirectory(string $directory)
    {
        // Find all integration directories
        $integrationDirs = File::directories($directory);

        $integrationDirs = array_filter($integrationDirs, function ($dir) {
            return !in_array(basename($dir), ['meta', 'contacts', 'other_excluded_folders']);
        });

        if (empty($integrationDirs)) {
            $this->command->warn("No integration directories found in: $directory");
            return;
        }

        $this->command->info("Found " . count($integrationDirs) . " integration directories to process");
        $this->command->newLine();

        foreach ($integrationDirs as $integrationDir) {
            $integrationName = basename($integrationDir);
            $this->processedRecords = [];

            $this->command->info("📁 Processing Integration: {$integrationName}");

            // Process main integration file
            $integrationFile = $integrationDir . '/' . Str::slug($integrationName) . '.php';

            if (!File::exists($integrationFile)) {
                $this->command->warn("  Main integration file not found: {$integrationFile}");
                continue;
            }

            $this->currentFile = basename($integrationFile);
            $data = require $integrationFile;

            if (!is_array($data)) {
                $this->command->warn("  Invalid data format in file");
                continue;
            }

            $totalItems = count($data);

            if ($totalItems > 0) {
                $bar = $this->command->getOutput()->createProgressBar($totalItems);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
                $bar->start();

                $fileStats = [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                ];

                $this->processIntegrations($data, $bar, $fileStats);

                $bar->finish();
                $this->command->newLine();

                $this->displayFileStats($fileStats);
            } else {
                $this->command->warn("  No valid data found in file");
            }

            // Process meta files in subdirectories
            $this->processMetaSubdirectories($integrationDir, $integrationName);

            $this->command->newLine();
        }
    }

    protected function processIntegrations(array $data, $progressBar = null, &$fileStats = [])
    {
        foreach ($data as $item) {
            $this->stats['total_processed']++;

            // Skip invalid items
            if (empty($item['name'])) {
                $this->stats['skipped']++;
                $fileStats['skipped']++;

                if ($progressBar) {
                    $progressBar->advance();
                }
                continue;
            }

            // Check if record exists
            $existingRecord = $this->findOrCacheRecord($item['name']);

            if ($existingRecord) {
                // Update existing record
                $updateData = [
                    'small_name' => $item['small_name'] ?? $existingRecord->small_name,
                    'nature' => $item['nature'] ?? $existingRecord->nature,
                    'status' => $item['status'] ?? $existingRecord->status,
                    'updated_by' => $item['updated_by'] ?? 0,
                ];

                $existingRecord->update($updateData);
                $record = $existingRecord;

                $this->stats['updated']++;
                $fileStats['updated']++;
            } else {
                // Create new record
                $record = SupportedIntegration::create([
                    'uid' => (string) Str::ulid(),
                    'name' => $item['name'],
                    'small_name' => $item['small_name'] ?? '',
                    'nature' => $item['nature'] ?? 'REST API',
                    'status' => $item['status'] ?? 'unknown',
                    'created_by' => $item['created_by'] ?? 0,
                    'updated_by' => $item['updated_by'] ?? 0,
                ]);

                // Cache the new record
                $this->processedRecords[$item['name']] = $record;

                $this->stats['created']++;
                $fileStats['created']++;
            }

            if ($progressBar) {
                $progressBar->advance();
            }
        }
    }

    protected function processMetaSubdirectories(string $integrationDir, string $integrationName)
    {
        // Find all subdirectories (like contacts, etc.)
        $subDirs = File::directories($integrationDir);

        if (empty($subDirs)) {
            $this->command->warn("  No meta subdirectories found for: {$integrationName}");
            return;
        }

        foreach ($subDirs as $subDir) {
            $category = basename($subDir);
            $this->command->info("  📂 Processing {$category} meta for: {$integrationName}");

            $metaFiles = File::glob("$subDir/*.php");

            if (empty($metaFiles)) {
                $this->command->warn("    No meta files found in: {$subDir}");
                continue;
            }

            $totalItems = count($metaFiles);

            if ($totalItems > 0) {
                $bar = $this->command->getOutput()->createProgressBar($totalItems);
                $bar->setFormat('     %current%/%max% [%bar%] %percent:3s%%');
                $bar->start();

                $fileStats = [
                    'metadata_created' => 0,
                    'metadata_skipped' => 0,
                ];

                foreach ($metaFiles as $file) {
                    $this->currentFile = basename($file);
                    $data = require $file;

                    if (!is_array($data)) {
                        $this->command->warn("    Invalid data format in meta file: {$this->currentFile}");
                        $bar->advance();
                        continue;
                    }

                    $this->processMetaData($integrationName, $data, $fileStats);
                    $bar->advance();
                }

                $bar->finish();
                $this->command->newLine();

                $this->displayMetaStats($fileStats, $integrationName, $category);
            }
        }
    }

    protected function processMetaData(string $integrationName, array $data, &$fileStats = [])
    {
        // Find the integration
        $integration = SupportedIntegration::where('name', $integrationName)->first();

        if (!$integration) {
            $this->command->warn("    Integration '{$integrationName}' not found. Skipping meta data.");
            return;
        }

        foreach ($data as $metaItem) {
            $this->stats['metadata_processed']++;

            // Skip invalid meta items
            if (empty($metaItem['key'])) {
                $this->stats['metadata_skipped']++;
                $fileStats['metadata_skipped']++;
                continue;
            }

            // Check if meta already exists
            $existingMeta = $integration->metas()->where('meta_key', $metaItem['key'])->first();

            if (!$existingMeta) {
                // Create new meta
                $integration->metas()->create([
                    'meta_key' => $metaItem['key'],
                    'meta_value' => $metaItem['value'] ?? '',
                    'status' => $metaItem['status'] ?? 'unknown',
                    'created_by' => $metaItem['created_by'] ?? 0,
                    'updated_by' => $metaItem['updated_by'] ?? 0,
                ]);

                $this->stats['metadata_created']++;
                $fileStats['metadata_created']++;
            } else {
                $this->stats['metadata_skipped']++;
                $fileStats['metadata_skipped']++;
            }
        }
    }

    /**
     * Find record by name, using cache to avoid duplicate database queries
     */
    private function findOrCacheRecord(string $name): ?SupportedIntegration
    {
        if (!isset($this->processedRecords[$name])) {
            $this->processedRecords[$name] = SupportedIntegration::where('name', $name)->first();
        }

        return $this->processedRecords[$name];
    }

    /**
     * Display stats for current file
     */
    private function displayFileStats(array $fileStats)
    {
        $this->command->info(" Integration Results:");
        $this->command->line("  Created: " . $fileStats['created']);
        $this->command->line("  Updated: " . $fileStats['updated']);
        $this->command->line("  Skipped: " . $fileStats['skipped']);
    }

    /**
     * Display stats for meta files
     */
    private function displayMetaStats(array $fileStats, string $integrationName, string $category)
    {
        $this->command->info("   Meta Results for {$integrationName} > {$category}:");
        $this->command->line("    Created: " . $fileStats['metadata_created']);
        $this->command->line("    Skipped: " . $fileStats['metadata_skipped']);
    }

    /**
     * Display final summary stats
     */
    private function displayFinalStats()
    {
        $this->command->newLine();
        $this->command->info('🎉 Integration seeding completed!');
        $this->command->newLine();

        $this->command->info(' Final Summary:');
        $this->command->line("  Total Integrations Processed: " . $this->stats['total_processed']);
        $this->command->line("  Integrations Created: " . $this->stats['created']);
        $this->command->line("  Integrations Updated: " . $this->stats['updated']);
        $this->command->line("  Integrations Skipped: " . $this->stats['skipped']);
        $this->command->line("  Total Metadata Processed: " . $this->stats['metadata_processed']);
        $this->command->line("  Metadata Created: " . $this->stats['metadata_created']);
        $this->command->line("  Metadata Skipped: " . $this->stats['metadata_skipped']);

        if ($this->stats['skipped'] > 0) {
            $this->command->newLine();
            $this->command->warn("⚠️  {$this->stats['skipped']} integrations were skipped due to missing or invalid names");
        }

        $this->command->newLine();
    }
}