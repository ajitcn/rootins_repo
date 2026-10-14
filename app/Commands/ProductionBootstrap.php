<?php

namespace App\Commands;

use App\Services\Production\ProductionBootstrapService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;
use Throwable;

/**
 * Initializes only the production identity, authorization, and reference data.
 * It never creates clinical, financial, inventory, or integration records.
 */
final class ProductionBootstrap extends BaseCommand
{
    protected $group = 'ROOTINS';
    protected $name = 'production:bootstrap';
    protected $description = 'Safely initialize required ROOTINS production data.';
    protected $usage = 'production:bootstrap [--dry-run] [--confirm-production]';
    protected $options = [
        '--dry-run' => 'Validate configuration and report planned changes without writing.',
        '--confirm-production' => 'Required before any database write is allowed.',
    ];

    public function run(array $params)
    {
        $dryRun = array_key_exists('dry-run', $params) || (bool) CLI::getOption('dry-run');
        $confirmed = array_key_exists('confirm-production', $params) || (bool) CLI::getOption('confirm-production');

        if (! $dryRun && ! $confirmed) {
            CLI::error('Refusing to write. Re-run with --confirm-production, or use --dry-run.');
            return EXIT_ERROR;
        }

        $input = [
            'name' => getenv('ROOTINS_ADMIN_NAME') ?: '',
            'email' => getenv('ROOTINS_ADMIN_EMAIL') ?: '',
            'password' => getenv('ROOTINS_ADMIN_PASSWORD') ?: '',
            'clinic_id' => getenv('ROOTINS_ADMIN_CLINIC_ID') ?: '',
        ];

        try {
            $summary = (new ProductionBootstrapService())->run($input, $dryRun);
            CLI::write($dryRun ? 'Production bootstrap dry run completed.' : 'Production bootstrap completed.', 'green');
            foreach (['created', 'existing', 'skipped', 'failed'] as $key) {
                CLI::write(ucfirst($key) . ': ' . (int) ($summary[$key] ?? 0));
            }

            if (! $dryRun) {
                log_message('info', 'Production bootstrap completed: created={created}, existing={existing}, skipped={skipped}, failed={failed}', $summary);
                service('audit')->record('production.bootstrap_completed', [
                    'created' => (int) ($summary['created'] ?? 0),
                    'existing' => (int) ($summary['existing'] ?? 0),
                    'skipped' => (int) ($summary['skipped'] ?? 0),
                    'failed' => (int) ($summary['failed'] ?? 0),
                ]);
            }

            return EXIT_SUCCESS;
        } catch (InvalidArgumentException $exception) {
            CLI::error($exception->getMessage());
            return EXIT_ERROR;
        } catch (Throwable $exception) {
            log_message('error', 'Production bootstrap failed: {class} at {file}:{line}', [
                'class' => $exception::class,
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
            ]);
            CLI::error('Production bootstrap failed safely. Check the application log for non-sensitive diagnostics.');
            return EXIT_ERROR;
        }
    }
}
