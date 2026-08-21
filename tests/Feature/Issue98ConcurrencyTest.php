<?php

namespace Tests\Feature;

use App\Enums\MasterKind;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectRabJasa;
use App\Models\Unit;
use App\Models\User;
use App\Services\CodedMasterLifecycle;
use App\Services\ProjectPlanningService;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Issue98ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_pgsql') || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('PostgreSQL and pcntl are required for concurrency verification.');
        }

        $this->artisan('migrate:fresh', ['--database' => 'migrator', '--force' => true]);
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--database' => 'migrator', '--force' => true]);
        parent::tearDown();
    }

    public function test_postgresql_serializes_concurrent_code_issuance(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');

        try {
            $actor = $this->thcWith('manage_master_data');
            $this->assertSame(0, DB::transactionLevel(), 'Concurrency fixtures must be committed before workers start.');

            $start = microtime(true) + 0.3;
            $firstCode = $this->forkWorker($start, function () use ($actor): array {
                $unit = app(CodedMasterLifecycle::class)->create(
                    User::query()->findOrFail($actor->id),
                    MasterKind::Unit,
                    ['nama' => 'Unit Concurrent A'],
                );

                return ['code' => $unit->kode];
            });
            $secondCode = $this->forkWorker($start, function () use ($actor): array {
                $unit = app(CodedMasterLifecycle::class)->create(
                    User::query()->findOrFail($actor->id),
                    MasterKind::Unit,
                    ['nama' => 'Unit Concurrent B'],
                );

                return ['code' => $unit->kode];
            });

            $codes = [$this->workerResult($firstCode)['code'], $this->workerResult($secondCode)['code']];
            sort($codes);
            $this->assertSame(['UNT-2608-0001', 'UNT-2608-0002'], $codes);
            $this->assertSame(2, Unit::query()->whereIn('kode', $codes)->count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_postgresql_serializes_direct_rab_add_against_first_baseline_publication(): void
    {
        $actor = $this->thcWith('manage_project_plan');
        [$project, $price] = $this->planningFixture();
        $projectId = $project->id;
        $priceId = $price->id;
        $actorId = $actor->id;
        $this->assertSame(0, DB::transactionLevel(), 'Concurrency fixtures must be committed before workers start.');
        DB::disconnect();
        DB::purge();
        DB::connection()->getPdo();
        app(TenantDatabaseContext::class)->set(null, true);
        $this->assertTrue(Project::query()->whereKey($projectId)->exists());

        $publication = $this->forkWorker(microtime(true) + 0.2, function () use ($projectId, $actorId): array {
            app(TenantDatabaseContext::class)->set(null, true);
            DB::beginTransaction();

            try {
                Project::query()->lockForUpdate()->findOrFail($projectId);
                usleep(600_000);
                app(ProjectPlanningService::class)->savePlan(
                    Project::query()->findOrFail($projectId),
                    User::query()->findOrFail($actorId),
                    '2026-09-30',
                    [['date' => '2026-09-30', 'percent' => '100']],
                );
                DB::commit();

                return ['status' => 'published'];
            } catch (\Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
        });
        $rabAdd = $this->forkWorker(microtime(true) + 0.35, function () use ($projectId, $priceId, $actorId): array {
            app(TenantDatabaseContext::class)->set(null, true);

            try {
                app(ProjectPlanningService::class)->addRabJasa(
                    Project::query()->findOrFail($projectId),
                    User::query()->findOrFail($actorId),
                    $priceId,
                    '1',
                );

                return ['status' => 'added'];
            } catch (ValidationException $exception) {
                return ['status' => array_key_first($exception->errors())];
            }
        });

        $this->assertSame('published', $this->workerResult($publication)['status']);
        $this->assertSame('rab_jasa', $this->workerResult($rabAdd)['status']);
        $this->asThc(function () use ($projectId): void {
            $this->assertSame(1, ProjectBaseline::query()->where('project_id', $projectId)->count());
            $this->assertSame(0, ProjectRabJasa::query()->where('project_id', $projectId)->count());
        });
    }

    /** @return array{pid:int,socket:resource} */
    private function forkWorker(float $startAt, \Closure $operation): array
    {
        DB::disconnect();
        DB::purge();
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Tidak dapat membuat worker concurrency.');
        }
        if ($pid === 0) {
            fclose($parentSocket);
            while (microtime(true) < $startAt) {
                usleep(10_000);
            }

            DB::purge();
            DB::connection()->getPdo();
            try {
                $payload = ['ok' => true, 'result' => $operation()];
            } catch (\Throwable $exception) {
                $payload = ['ok' => false, 'error' => $exception::class.': '.$exception->getMessage()];
            }
            fwrite($childSocket, json_encode($payload, JSON_THROW_ON_ERROR));
            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);

        return ['pid' => $pid, 'socket' => $parentSocket];
    }

    private function workerResult(array $worker): array
    {
        $payload = json_decode(stream_get_contents($worker['socket']), true, flags: JSON_THROW_ON_ERROR);
        fclose($worker['socket']);
        pcntl_waitpid($worker['pid'], $status);
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertTrue($payload['ok'], $payload['error'] ?? 'Worker concurrency gagal.');

        return $payload['result'];
    }

    /** @return array{Project, MitraHargaJasa} */
    private function planningFixture(): array
    {
        $mitra = Mitra::factory()->create();

        return $this->asThc(function () use ($mitra): array {
            $project = Project::query()->create([
                'id_project' => 'PRJ-CONCURRENT-0098',
                'nama' => 'Project Concurrent',
                'mitra_id' => $mitra->id,
            ]);
            $job = PekerjaanJasa::query()->create(['kode' => 'JASA-CONCURRENT', 'nama' => 'Jasa Concurrent']);
            $pks = Pks::query()->create([
                'mitra_id' => $mitra->id,
                'nomor' => 'PKS-CONCURRENT-0098',
                'tanggal_mulai' => '2026-01-01',
                'tanggal_berakhir' => '2026-12-31',
            ]);
            $price = MitraHargaJasa::query()->create([
                'mitra_id' => $mitra->id,
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '100000.00',
                'status' => 'disetujui',
                'berlaku_mulai' => '2026-01-01',
            ]);

            return [$project, $price];
        });
    }

    private function thcWith(string ...$permissions): User
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));
        }

        return User::factory()->create(['mitra_id' => null, 'grup_id' => $group->id]);
    }

    private function asThc(\Closure $callback): mixed
    {
        DB::connection()->getPdo();
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
