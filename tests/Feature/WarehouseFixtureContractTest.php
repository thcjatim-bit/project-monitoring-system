<?php

namespace Tests\Feature;

use App\Models\Mitra;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use ReflectionProperty;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\Support\MitraFillingWarehouseFactory;
use Tests\TestCase;

/**
 * ADR-0023: pada `warehouses`, `mitra_id IS NULL` berarti "milik THC" — arti yang tidak
 * boleh tertukar dengan "tidak terisi". Fixture bersama karena itu harus meneruskan
 * `mitra_id` apa adanya, termasuk NULL, alih-alih menyerahkannya ke WarehouseFactory.
 */
class WarehouseFixtureContractTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    public function test_gudang_thc_dari_fixture_ber_mitra_id_null_walau_factory_mengisinya(): void
    {
        $mitra = Mitra::factory()->create();
        MitraFillingWarehouseFactory::$mitraId = $mitra->id;
        $resolver = new ReflectionProperty(Factory::class, 'factoryNameResolver');
        $resolverAsal = $resolver->getValue();
        Factory::guessFactoryNamesUsing(fn (string $model): string => $model === Warehouse::class
            ? MitraFillingWarehouseFactory::class
            : Factory::$namespace.class_basename($model).'Factory');

        try {
            $this->assertSame(
                $mitra->id,
                Warehouse::factory()->make()->mitra_id,
                'factory pengganti harus benar-benar mengisi mitra_id, kalau tidak test ini tidak membuktikan apa pun',
            );

            $gudangThc = $this->warehouse(null, 'WH-THC-EKSPLISIT');
            $gudangMitra = $this->warehouse($mitra, 'WH-MITRA-EKSPLISIT');
        } finally {
            $resolver->setValue(null, $resolverAsal);
            MitraFillingWarehouseFactory::$mitraId = null;
        }

        $this->assertNull($gudangThc->mitra_id);
        $this->assertSame($mitra->id, $gudangMitra->mitra_id);

        [$tersimpanThc, $tersimpanMitra] = $this->asThc(fn (): array => [
            Warehouse::query()->whereKey($gudangThc->id)->value('mitra_id'),
            Warehouse::query()->whereKey($gudangMitra->id)->value('mitra_id'),
        ]);

        $this->assertNull($tersimpanThc);
        $this->assertSame($mitra->id, $tersimpanMitra);
    }
}
