<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VinkiusLabs\Markovable\Console\Concerns\FormatsBytes;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Models\MarkovableModelSnapshot;
use VinkiusLabs\Markovable\Support\ModelMetrics;

class SnapshotCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'markovable:snapshot
        {model : The cached model key to snapshot}
        {--tag= : Version tag applied to the snapshot}
        {--description= : Snapshot description}
        {--storage=database : Target storage (database, file, disk:name)}
        {--compress : Compress the snapshot payload}
        {--encrypt : Encrypt the snapshot payload using Laravel encryption}
        {--from-storage= : Storage driver storing the active model}
        {--output-path= : Custom path when using file or disk storage}';

    protected $description = 'Create a versioned snapshot of a cached Markovable model.';

    public function handle(): int
    {
        $modelKey = (string) $this->argument('model');
        $targetStorage = $this->option('storage') ?: 'database';
        $sourceStorage = $this->option('from-storage') ?: config('markovable.storage', 'cache');
        $tag = $this->option('tag') ?: now()->format('YmdHis');
        $description = $this->option('description');
        $compress = (bool) $this->option('compress');
        $encrypt = (bool) $this->option('encrypt');

        $this->info("📸 Creating snapshot: {$modelKey}");
        $this->info("🏷️ Tag: {$tag}");

        if ($description) {
            $this->info("📝 Description: {$description}");
        }

        $manager = $this->resolveManager();
        $payload = $manager->storage($sourceStorage)->get($modelKey);

        if ($payload === null) {
            $this->error("Model {$modelKey} was not found in {$sourceStorage} storage.");

            return Command::FAILURE;
        }

        $context = $payload['context'] ?? 'text';
        $chain = Markovable::chain($context)->useStorage($sourceStorage)->cache($modelKey);
        $chain->toProbabilities();
        $metrics = ModelMetrics::fromChain($chain);

        $serialized = serialize($payload);
        $originalSize = strlen($serialized);
        $finalPayload = $serialized;
        $storedSize = $originalSize;

        if ($compress) {
            $this->line('🗜️ Compressing...');
            $finalPayload = gzencode($finalPayload, 9);
            $storedSize = strlen($finalPayload);
            $this->info('Done ('.$this->formatBytes($originalSize).' → '.$this->formatBytes($storedSize).')');
        }

        $encoded = false;

        if ($encrypt) {
            $this->line('🔐 Encrypting...');
            $finalPayload = encrypt($finalPayload);
            $storedSize = strlen($finalPayload);
            $this->info('Done');
        } elseif ($compress) {
            $finalPayload = base64_encode($finalPayload);
            $storedSize = strlen($finalPayload);
            $encoded = true;
        }

        $metadata = array_merge($metrics->toArray(), [
            'model_key' => $modelKey,
            'tag' => $tag,
            'compressed' => $compress,
            'encrypted' => $encrypt,
            'encoded' => $encoded,
            'context' => $context,
            'source_storage' => $sourceStorage,
            'original_size' => $originalSize,
            'stored_size' => $storedSize,
        ]);

        $result = $this->persistSnapshot(
            $targetStorage,
            $modelKey,
            $tag,
            $description,
            $finalPayload,
            $metadata
        );

        $this->info('💾 Saved to: '.$result['location']);
        $this->info('🆔 Snapshot ID: '.$result['identifier']);

        $this->newLine();
        $this->displaySnapshotDetails($metadata);

        return Command::SUCCESS;
    }

    private function persistSnapshot(string $storage, string $modelKey, string $tag, ?string $description, string $payload, array $metadata): array
    {
        return match (true) {
            $storage === 'database' => $this->storeInDatabase($modelKey, $tag, $description, $payload, $metadata),
            $storage === 'file' => $this->storeOnDisk('local', $modelKey, $tag, $payload, $metadata),
            str_starts_with($storage, 'disk:') => $this->storeOnDisk(substr($storage, 5), $modelKey, $tag, $payload, $metadata),
            default => $this->storeOnDisk($storage, $modelKey, $tag, $payload, $metadata),
        };
    }

    private function storeInDatabase(string $modelKey, string $tag, ?string $description, string $payload, array $metadata): array
    {
        $snapshot = MarkovableModelSnapshot::create([
            'model_key' => $modelKey,
            'tag' => $tag,
            'description' => $description,
            'storage' => 'database',
            'compressed' => (bool) $metadata['compressed'],
            'encrypted' => (bool) $metadata['encrypted'],
            'original_size' => $metadata['original_size'],
            'stored_size' => $metadata['stored_size'],
            'payload' => $payload,
            'metadata' => $metadata,
        ]);

        return [
            'location' => 'database (markovable_model_snapshots)',
            'identifier' => $snapshot->identifier,
        ];
    }

    private function storeOnDisk(string $disk, string $modelKey, string $tag, string $payload, array $metadata): array
    {
        $path = $this->option('output-path');

        if (! $path) {
            $filename = Str::slug($modelKey.'-'.$tag).'.snapshot';
            $directory = 'markovable/snapshots/'.Str::slug($modelKey);
            $path = $directory.'/'.$filename;
        }

        $contents = json_encode([
            'payload' => $payload,
            'metadata' => $metadata,
        ], JSON_PRETTY_PRINT);

        if ($contents === false) {
            throw new InvalidArgumentException('Failed to encode snapshot payload for disk storage.');
        }

        Storage::disk($disk)->put($path, $contents);

        return [
            'location' => $disk.':'.$path,
            'identifier' => $path,
        ];
    }

    private function displaySnapshotDetails(array $metadata): void
    {
        $this->info('Snapshot Details:');
        $this->line('  - States: '.number_format((int) ($metadata['states'] ?? 0)));
        $this->line('  - Transitions: '.number_format((int) ($metadata['transitions'] ?? 0)));
        $this->line('  - Size (raw): '.$this->formatBytes((int) ($metadata['original_size'] ?? 0)));
        $this->line('  - Size (stored): '.$this->formatBytes((int) ($metadata['stored_size'] ?? 0)));
        $this->line('  - Confidence: '.number_format((float) ($metadata['confidence'] ?? 0), 2));
        $this->line('  - Created at: '.now()->toDateTimeString());
    }

    private function resolveManager(): MarkovableManager
    {
        return app(MarkovableManager::class);
    }
}
