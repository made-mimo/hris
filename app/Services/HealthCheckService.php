<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Spec A5: "verification of required runtime dependencies, permissions, and connectivity." */
class HealthCheckService
{
    public function run(): array
    {
        return [
            $this->check('Database connection', fn () => DB::connection()->getPdo() !== null),
            $this->check('Storage disk writable', function () {
                $path = 'health-check-'.uniqid().'.tmp';
                Storage::disk('local')->put($path, 'ok');
                $ok = Storage::disk('local')->exists($path);
                Storage::disk('local')->delete($path);

                return $ok;
            }),
            $this->check('Public storage symlink', fn () => is_link(public_path('storage')) || is_dir(public_path('storage'))),
            $this->check('Queue connection configured', fn () => config('queue.default') !== null),
            $this->check('Application key set', fn () => config('app.key') !== null && config('app.key') !== ''),
            $this->check('Cache store reachable', function () {
                cache()->put('health-check', true, 5);

                return cache()->get('health-check') === true;
            }),
        ];
    }

    protected function check(string $label, callable $test): array
    {
        try {
            $ok = (bool) $test();

            return ['label' => $label, 'ok' => $ok, 'detail' => $ok ? 'OK' : 'Check returned false'];
        } catch (\Throwable $e) {
            return ['label' => $label, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
