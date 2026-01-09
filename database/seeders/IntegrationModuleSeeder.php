<?php

namespace Iquesters\Integration\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Iquesters\Integration\Models\SupportedIntegration;

class IntegrationModuleSeeder
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    private array $stats = [
        'total_processed'     => 0,
        'created'             => 0,
        'updated'             => 0,
        'skipped'             => 0,
        'metadata_processed'  => 0,
        'metadata_created'    => 0,
        'metadata_skipped'    => 0,
    ];

    private array $processedRecords = [];
    private string $currentFile = '';

    public function run(): void
    {
        $this->command->info('Starting Integration seeding...');
        $this->command->newLine();

        $this->seedFromDirectory(__DIR__);

        $this->displayFinalStats();
    }

    protected function seedFromDirectory(string $directory): void
    {
        $integrationDirs = File::directories($directory);

        $integrationDirs = array_filter($integrationDirs, function ($dir) {
            return !in_array(basename($dir), ['meta', 'contacts', 'other_excluded_folders']);
        });

        if (empty($integrationDirs)) {
            $this->command->warn("No integration directories found in: {$directory}");
            return;
        }

        $this->command->info('Found ' . count($integrationDirs) . ' integration directories to process');
        $this->command->newLine();

        foreach ($integrationDirs as $integrationDir) {
            $integrationName = basename($integrationDir);
            $this->processedRecords = [];

            $this->command->info("📁 Processing Integration: {$integrationName}");

            $integrationFile = $integrationDir . '/' . Str::slug($integrationName) . '.php';

            if (!File::exists($integrationFile)) {
                $this->command->warn("  Main integration file not found: {$integrationFile}");
                continue;
            }

            $this->currentFile = basename($integrationFile);
            $data = require $integrationFile;

            if (!is_array($data)) {
                $this->command->warn('  Invalid data format in file');
                continue;
            }

            $totalItems = count($data);

            if ($totalItems === 0) {
                $this->command->warn('  No valid data found in file');
                continue;
            }

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

            $this->processMetaSubdirectories($integrationDir, $integrationName);

            $this->command->newLine();
        }
    }

    protected function processIntegrations(array $data, $progressBar, array &$fileStats): void
    {
        foreach ($data as $item) {
            $this->stats['total_processed']++;

            if (empty($item['name'])) {
                $this->stats['skipped']++;
                $fileStats['skipped']++;
                $progressBar->advance();
                continue;
            }

            $existingRecord = $this->findOrCacheRecord($item['name']);

            if ($existingRecord) {
                $existingRecord->update([
                    'small_name' => $item['small_name'] ?? $existingRecord->small_name,
                    'nature'     => $item['nature'] ?? $existingRecord->nature,
                    'status'     => $item['status'] ?? $existingRecord->status,
                    'updated_by'=> $item['updated_by'] ?? 0,
                ]);

                $this->stats['updated']++;
                $fileStats['updated']++;
            } else {
                $record = SupportedIntegration::create([
                    'uid'         => (string) Str::ulid(),
                    'name'        => $item['name'],
                    'small_name'  => $item['small_name'] ?? '',
                    'nature'      => $item['nature'] ?? 'REST API',
                    'category'    => $item['category'] ?? '',
                    'status'      => $item['status'] ?? 'unknown',
                    'created_by'  => $item['created_by'] ?? 0,
                    'updated_by'  => $item['updated_by'] ?? 0,
                ]);

                $this->processedRecords[$item['name']] = $record;

                $this->stats['created']++;
                $fileStats['created']++;
            }

            $progressBar->advance();
        }
    }

    protected function processMetaSubdirectories(string $integrationDir, string $integrationName): void
    {
        $subDirs = File::directories($integrationDir);

        if (empty($subDirs)) {
            $this->command->warn("  No meta subdirectories found for: {$integrationName}");
            return;
        }

        foreach ($subDirs as $subDir) {
            $category = basename($subDir);
            $this->command->info("  📂 Processing {$category} meta for: {$integrationName}");

            $metaFiles = File::glob("{$subDir}/*.php");

            if (empty($metaFiles)) {
                $this->command->warn("    No meta files found in: {$subDir}");
                continue;
            }

            $bar = $this->command->getOutput()->createProgressBar(count($metaFiles));
            $bar->setFormat('     %current%/%max% [%bar%] %percent:3s%%');
            $bar->start();

            $fileStats = [
                'metadata_created' => 0,
                'metadata_skipped' => 0,
            ];

            foreach ($metaFiles as $file) {
                $this->currentFile = basename($file);
                $data = require $file;

                if (is_array($data)) {
                    $this->processMetaData($integrationName, $data, $fileStats);
                } else {
                    $this->command->warn("    Invalid data format in meta file: {$this->currentFile}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->command->newLine();

            $this->displayMetaStats($fileStats, $integrationName, $category);
        }
    }

    protected function processMetaData(string $integrationName, array $data, array &$fileStats): void
    {
        $integration = SupportedIntegration::where('name', $integrationName)->first();

        if (!$integration) {
            $this->command->warn("    Integration '{$integrationName}' not found. Skipping meta data.");
            return;
        }

        foreach ($data as $metaItem) {
            $this->stats['metadata_processed']++;

            if (empty($metaItem['key'])) {
                $this->stats['metadata_skipped']++;
                $fileStats['metadata_skipped']++;
                continue;
            }

            $exists = $integration->metas()
                ->where('meta_key', $metaItem['key'])
                ->exists();

            if (!$exists) {
                $integration->metas()->create([
                    'meta_key'   => $metaItem['key'],
                    'meta_value' => $metaItem['value'] ?? '',
                    'status'     => $metaItem['status'] ?? 'unknown',
                    'created_by'=> $metaItem['created_by'] ?? 0,
                    'updated_by'=> $metaItem['updated_by'] ?? 0,
                ]);

                $this->stats['metadata_created']++;
                $fileStats['metadata_created']++;
            } else {
                $this->stats['metadata_skipped']++;
                $fileStats['metadata_skipped']++;
            }
        }
    }

    private function findOrCacheRecord(string $name): ?SupportedIntegration
    {
        return $this->processedRecords[$name]
            ??= SupportedIntegration::where('name', $name)->first();
    }

    private function displayFileStats(array $fileStats): void
    {
        $this->command->info(' Integration Results:');
        $this->command->line('  Created: ' . $fileStats['created']);
        $this->command->line('  Updated: ' . $fileStats['updated']);
        $this->command->line('  Skipped: ' . $fileStats['skipped']);
    }

    private function displayMetaStats(array $fileStats, string $integrationName, string $category): void
    {
        $this->command->info("   Meta Results for {$integrationName} > {$category}:");
        $this->command->line('    Created: ' . $fileStats['metadata_created']);
        $this->command->line('    Skipped: ' . $fileStats['metadata_skipped']);
    }

    private function displayFinalStats(): void
    {
        $this->command->newLine();
        $this->command->info('🎉 Integration seeding completed!');
        $this->command->newLine();

        $this->command->info(' Final Summary:');
        $this->command->line('  Total Integrations Processed: ' . $this->stats['total_processed']);
        $this->command->line('  Integrations Created: ' . $this->stats['created']);
        $this->command->line('  Integrations Updated: ' . $this->stats['updated']);
        $this->command->line('  Integrations Skipped: ' . $this->stats['skipped']);
        $this->command->line('  Total Metadata Processed: ' . $this->stats['metadata_processed']);
        $this->command->line('  Metadata Created: ' . $this->stats['metadata_created']);
        $this->command->line('  Metadata Skipped: ' . $this->stats['metadata_skipped']);
    }
}