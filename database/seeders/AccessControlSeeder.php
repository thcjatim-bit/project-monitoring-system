<?php

namespace Database\Seeders;

use App\Models\Grup;
use App\Models\Izin;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $izins = collect([
            'read_dashboard' => 'Melihat Dashboard',
            'read_project' => 'Melihat Project',
            'create_project' => 'Menambah Project',
            'update_project' => 'Mengubah Project',
            'delete_project' => 'Menghapus Project',
            'manage_users' => 'Mengelola User',
            'manage_grups' => 'Mengelola Grup',
            'manage_mitras' => 'Mengelola Mitra',
            'manage_warehouses' => 'Mengelola Warehouse',
            'manage_materials' => 'Mengelola Material',
            'operate_warehouse' => 'Mengoperasikan Warehouse',
            'manage_master_data' => 'Mengelola Master Data',
            'read_master_data' => 'Melihat Master Data',
        ])->mapWithKeys(fn (string $nama, string $kode) => [
            $kode => Izin::query()->firstOrCreate(['kode' => $kode], ['nama' => $nama]),
        ]);

        $matriks = [
            'admin_thc' => ['nama' => 'Admin THC', 'izins' => $izins->keys()->all()],
            'pm' => ['nama' => 'PM', 'izins' => ['read_dashboard', 'read_project', 'create_project', 'update_project', 'read_master_data']],
            'waspang' => ['nama' => 'Waspang', 'izins' => ['read_dashboard', 'read_project', 'read_master_data']],
            'viewer' => ['nama' => 'Viewer', 'izins' => ['read_dashboard', 'read_project', 'read_master_data']],
            'mitra' => ['nama' => 'Mitra', 'izins' => ['read_dashboard', 'read_project', 'read_master_data']],
        ];

        foreach ($matriks as $preset => $definition) {
            $grup = Grup::query()->firstOrCreate(['preset' => $preset], ['nama' => $definition['nama']]);
            $grup->izins()->sync($izins->only($definition['izins'])->pluck('id'));
        }
    }
}
