<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportSqliteData extends Command
{
    protected $signature = 'outreach:import-sqlite
        {target-env : File with the target DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD}
        {--source=database/database.sqlite : SQLite database to copy from}
        {--dry-run : Compare source and target without writing anything}';

    protected $description = 'Copy local SQLite data into an empty, migrated MySQL database, keeping IDs';

    // Framework working state that must not be replayed on the server.
    private const SKIPPED = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks', 'sessions', 'password_reset_tokens'];

    public function handle(): int
    {
        $this->connect();
        $source = DB::connection('import_source');
        $target = DB::connection('import_target');

        $tables = collect($source->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"))
            ->pluck('name')
            ->reject(fn ($table) => in_array($table, self::SKIPPED))
            ->values();

        $plan = [];
        $problems = [];
        foreach ($tables as $table) {
            if (! Schema::connection('import_target')->hasTable($table)) {
                $problems[] = "$table is missing from the target; run the migrations there first.";

                continue;
            }
            $targetColumns = collect(Schema::connection('import_target')->getColumns($table))->keyBy('name');
            $sourceColumns = collect(Schema::connection('import_source')->getColumns($table))->pluck('name');
            $dropped = $sourceColumns->diff($targetColumns->keys());
            $rows = $source->table($table)->count();
            $existing = $target->table($table)->count();
            if ($existing > 0) {
                $problems[] = "$table already has $existing rows in the target; the target must be empty.";
            }
            if ($rows > 0 && $dropped->isNotEmpty()) {
                $problems[] = "$table has source columns the target lacks: ".$dropped->implode(', ');
            }
            $plan[$table] = ['rows' => $rows, 'existing' => $existing, 'columns' => $targetColumns->only($sourceColumns)];
        }

        $this->table(['Table', 'Source rows', 'Target rows'], collect($plan)->map(fn ($p, $t) => [$t, $p['rows'], $p['existing']])->values());
        $this->line('Skipped: '.implode(', ', self::SKIPPED));

        if ($problems) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run only: nothing was written.');

            return self::SUCCESS;
        }

        // The source enforced its foreign keys, so rows can go in table by table.
        $target->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $target->transaction(function () use ($plan, $source, $target) {
                foreach ($plan as $table => $p) {
                    $columns = $p['columns'];
                    $source->table($table)->orderBy($columns->has('id') ? 'id' : $columns->keys()->first())
                        ->select($columns->keys()->all())
                        ->chunk(200, function ($rows) use ($table, $columns, $target) {
                            $target->table($table)->insert($rows->map(fn ($row) => $this->convert((array) $row, $columns, $table))->all());
                        });
                }
                // Imported data stays inert until it has been checked on the server.
                $target->table('employer_campaigns')->update(['enabled' => false]);
            });
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        foreach ($plan as $table => $p) {
            $copied = $target->table($table)->count();
            if ($copied !== $p['rows']) {
                $this->error("$table: expected {$p['rows']} rows, found $copied.");

                return self::FAILURE;
            }
        }
        $this->info('Imported '.collect($plan)->sum('rows').' rows. Employer campaigns were switched off on the target.');

        return self::SUCCESS;
    }

    private function connect(): void
    {
        $path = $this->argument('target-env');
        if (! is_file($path)) {
            throw new RuntimeException("Target settings file not found: $path");
        }
        $env = Dotenv::parse(file_get_contents($path));
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            if (! isset($env[$key]) || $env[$key] === '') {
                throw new RuntimeException("$key is missing from $path");
            }
        }
        $source = realpath($this->option('source'));
        if (! $source) {
            throw new RuntimeException('Source SQLite database not found.');
        }

        config(['database.connections.import_source' => ['driver' => 'sqlite', 'database' => $source, 'prefix' => '', 'foreign_key_constraints' => true]]);
        config(['database.connections.import_target' => array_merge(config('database.connections.mysql'), [
            'url' => null,
            'host' => $env['DB_HOST'],
            'port' => $env['DB_PORT'],
            'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'],
            'password' => $env['DB_PASSWORD'],
        ])]);

        try {
            DB::connection('import_target')->getPdo();
        } catch (Throwable $e) {
            throw new RuntimeException('Could not connect to the target database (is this IP a trusted source?): '.$e->getMessage());
        }
    }

    /** MySQL strict mode rejects some values SQLite accepted. */
    private function convert(array $row, $columns, string $table): array
    {
        foreach ($row as $name => $value) {
            if ($value === null) {
                continue;
            }
            $type = strtolower($columns[$name]['type_name']);
            if (in_array($type, ['datetime', 'timestamp']) && ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                $row[$name] = Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
            } elseif ($type === 'date' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $row[$name] = Carbon::parse($value)->utc()->format('Y-m-d');
            } elseif ($type === 'json' && json_decode($value) === null && $value !== 'null') {
                throw new RuntimeException("$table.$name (id ".($row['id'] ?? '?').') holds invalid JSON.');
            }
        }

        return $row;
    }
}
