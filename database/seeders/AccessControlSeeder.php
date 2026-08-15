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
            'manage_project_plan' => 'Mengelola Rencana Project',
            'read_project_progress' => 'Melihat Progres Project',
            'report_project_progress' => 'Melaporkan Progres Project',
            'verify_project_progress' => 'Memverifikasi Progres Project',
            'update_project_step' => 'Mengubah Step Project',
            'read_project_material' => 'Melihat Kesiapan Material Project',
            'manage_project_material' => 'Mengelola RAB Material Project',
            'upload_project_photo' => 'Mengunggah Foto Pekerjaan',
            'manage_users' => 'Mengelola User',
            'manage_grups' => 'Mengelola Grup',
            'manage_mitras' => 'Mengelola Mitra',
            'manage_warehouses' => 'Mengelola Warehouse',
            'manage_materials' => 'Mengelola Material',
            'operate_warehouse' => 'Mengoperasikan Warehouse',
            'manage_master_data' => 'Mengelola Master Data',
            'read_master_data' => 'Melihat Master Data',
            'read_material_request' => 'Melihat Request Material',
            'create_material_request' => 'Mengajukan Request Material',
            'approve_material_request' => 'Memutuskan Request Material',
        ])->mapWithKeys(fn (string $nama, string $kode) => [
            $kode => Izin::query()->firstOrCreate(['kode' => $kode], ['nama' => $nama]),
        ]);

        $matriks = [
            'admin_thc' => ['nama' => 'Admin THC', 'izins' => $izins->keys()->all()],
            'pm' => ['nama' => 'PM', 'izins' => ['read_dashboard', 'read_project', 'create_project', 'update_project', 'manage_project_plan', 'read_project_progress', 'verify_project_progress', 'update_project_step', 'read_project_material', 'manage_project_material', 'upload_project_photo', 'read_master_data', 'read_material_request']],
            'waspang' => ['nama' => 'Waspang', 'izins' => ['read_dashboard', 'read_project', 'read_project_progress', 'report_project_progress', 'update_project_step', 'read_project_material', 'upload_project_photo', 'read_master_data', 'read_material_request']],
            'viewer' => ['nama' => 'Viewer', 'izins' => ['read_dashboard', 'read_project', 'read_project_progress', 'read_project_material', 'read_master_data']],
            'mitra' => ['nama' => 'Mitra', 'izins' => ['read_dashboard', 'read_project', 'read_project_progress', 'report_project_progress', 'update_project_step', 'read_project_material', 'upload_project_photo', 'read_master_data', 'read_material_request', 'create_material_request']],
        ];

        foreach ($matriks as $preset => $definition) {
            $grup = Grup::query()->firstOrCreate(['preset' => $preset], ['nama' => $definition['nama']]);
            $grup->izins()->sync($izins->only($definition['izins'])->pluck('id'));
        }
    }
}
