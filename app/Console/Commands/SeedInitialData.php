<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Dinas;
use App\Models\Puskesmas;
use App\Models\TahunProgram;
use App\Models\RefJenisKelamin;
use App\Models\RefJenisProgram;
use App\Models\RefStatus;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SeedInitialData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-initial-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed data awal aplikasi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai seed data awal...');
        
        // Seed roles and permissions
        $this->seedRolesAndPermissions();
        
        // Seed data Dinas
        $this->seedDinas();
        
        // Seed data Puskesmas
        $this->seedPuskesmas();
        
        // Seed data User
        $this->seedUsers();
        
        // Seed data Tahun Program
        $this->seedTahunProgram();
        
        // Seed data Referensi
        $this->seedReferensi();
        
        $this->info('Seed data awal selesai!');
    }
    
    /**
     * Seed roles and permissions
     */
    private function seedRolesAndPermissions()
    {
        $this->info('Seeding roles and permissions...');
        
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $dinasRole = Role::firstOrCreate(['name' => 'dinas']);
        $puskesmasRole = Role::firstOrCreate(['name' => 'puskesmas']);
        
        // Create permissions
        $manageUsers = Permission::firstOrCreate(['name' => 'manage users']);
        $manageTargets = Permission::firstOrCreate(['name' => 'manage targets']);
        $approveReports = Permission::firstOrCreate(['name' => 'approve reports']);
        $submitReports = Permission::firstOrCreate(['name' => 'submit reports']);
        $viewReports = Permission::firstOrCreate(['name' => 'view reports']);
        $managePatients = Permission::firstOrCreate(['name' => 'manage patients']);
        
        // Assign permissions to roles
        $adminRole->syncPermissions([
            $manageUsers, $manageTargets, $approveReports, $viewReports, $submitReports, $managePatients
        ]);
        
        $dinasRole->syncPermissions([
            $manageTargets, $approveReports, $viewReports
        ]);
        
        $puskesmasRole->syncPermissions([
            $submitReports, $managePatients, $viewReports
        ]);
        
        $this->info('Roles and permissions berhasil ditambahkan.');
    }
    
    /**
     * Seed data Dinas
     */
    private function seedDinas()
    {
        $this->info('Seeding data Dinas...');
        
        if (Dinas::count() === 0) {
            Dinas::create([
                'kode' => 'DK001',
                'nama' => 'Dinas Kesehatan Kabupaten/Kota',
                'alamat' => 'Jl. Kesehatan No. 1',
            ]);
            
            $this->info('Data Dinas berhasil ditambahkan.');
        } else {
            $this->info('Data Dinas sudah ada, skip seeding.');
        }
    }
    
    /**
     * Seed data Puskesmas
     */
    private function seedPuskesmas()
    {
        $this->info('Seeding data Puskesmas...');
        
        if (Puskesmas::count() === 0) {
            $dinas = Dinas::first();
            
            if (!$dinas) {
                $this->error('Data Dinas tidak ditemukan!');
                return;
            }
            
            // Buat 25 puskesmas
            for ($i = 1; $i <= 25; $i++) {
                $kode = 'PKM' . str_pad($i, 3, '0', STR_PAD_LEFT);
                
                Puskesmas::create([
                    'dinas_id' => $dinas->id,
                    'kode' => $kode,
                    'nama' => 'Puskesmas ' . $i,
                    'alamat' => 'Jl. Puskesmas No. ' . $i,
                ]);
            }
            
            $this->info('25 data Puskesmas berhasil ditambahkan.');
        } else {
            $this->info('Data Puskesmas sudah ada, skip seeding.');
        }
    }
    
    /**
     * Seed data User
     */
    private function seedUsers()
    {
        $this->info('Seeding data User...');
        
        $dinas = Dinas::first();
        
        if (!$dinas) {
            $this->error('Data Dinas tidak ditemukan!');
            return;
        }
        
        if (User::count() === 0) {
            // Buat user admin
            $admin = User::create([
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'nama_puskesmas' => 'Administrator',
                'isadmin' => true,
                'dinas_id' => $dinas->id,
            ]);
            $admin->assignRole('admin');
            
            // Buat user dinas
            $dinasUser = User::create([
                'username' => 'dinas',
                'password' => Hash::make('dinas123'),
                'nama_puskesmas' => 'Dinas Kesehatan',
                'isadmin' => true,
                'dinas_id' => $dinas->id,
            ]);
            $dinasUser->assignRole('dinas');
            
            // Buat user untuk setiap puskesmas
            $puskesmas = Puskesmas::all();
            
            foreach ($puskesmas as $p) {
                $puskesmasUser = User::create([
                    'username' => strtolower(str_replace(' ', '', $p->kode)),
                    'password' => Hash::make('puskesmas123'),
                    'nama_puskesmas' => $p->nama,
                    'isadmin' => false,
                    'dinas_id' => null,
                ]);
                $puskesmasUser->assignRole('puskesmas');
            }
            
            $this->info('Data User berhasil ditambahkan.');
        } else {
            $this->info('Data User sudah ada, skip seeding.');
        }
    }
    
    /**
     * Seed data Tahun Program
     */
    private function seedTahunProgram()
    {
        $this->info('Seeding data Tahun Program...');
        
        if (TahunProgram::count() === 0) {
            $currentYear = date('Y');
            
            TahunProgram::create([
                'tahun' => $currentYear,
                'nama' => 'Program Tahun ' . $currentYear,
                'tanggal_mulai' => $currentYear . '-01-01',
                'tanggal_selesai' => $currentYear . '-12-31',
                'is_active' => true,
                'keterangan' => 'Program aktif tahun ' . $currentYear,
            ]);
            
            $this->info('Data Tahun Program berhasil ditambahkan.');
        } else {
            $this->info('Data Tahun Program sudah ada, skip seeding.');
        }
    }
    
    /**
     * Seed data Referensi
     */
    private function seedReferensi()
    {
        $this->info('Seeding data Referensi...');
        
        // Jenis Kelamin
        if (RefJenisKelamin::count() === 0) {
            RefJenisKelamin::create([
                'kode' => 'L',
                'nama' => 'Laki-laki',
            ]);
            
            RefJenisKelamin::create([
                'kode' => 'P',
                'nama' => 'Perempuan',
            ]);
            
            $this->info('Data Referensi Jenis Kelamin berhasil ditambahkan.');
        } else {
            $this->info('Data Referensi Jenis Kelamin sudah ada, skip seeding.');
        }
        
        // Jenis Program
        if (RefJenisProgram::count() === 0) {
            RefJenisProgram::create([
                'kode' => 'HT',
                'nama' => 'Hipertensi',
                'keterangan' => 'Program pengendalian hipertensi',
            ]);
            
            RefJenisProgram::create([
                'kode' => 'DM',
                'nama' => 'Diabetes Mellitus',
                'keterangan' => 'Program pengendalian diabetes mellitus',
            ]);
            
            $this->info('Data Referensi Jenis Program berhasil ditambahkan.');
        } else {
            $this->info('Data Referensi Jenis Program sudah ada, skip seeding.');
        }
        
        // Status
        if (RefStatus::count() === 0) {
            // Status Laporan
            RefStatus::create([
                'kode' => 'DRAFT',
                'nama' => 'Draft',
                'kategori' => 'laporan',
                'keterangan' => 'Laporan dalam proses penyusunan',
            ]);
            
            RefStatus::create([
                'kode' => 'SUBMITTED',
                'nama' => 'Submitted',
                'kategori' => 'laporan',
                'keterangan' => 'Laporan sudah disubmit',
            ]);
            
            RefStatus::create([
                'kode' => 'APPROVED',
                'nama' => 'Approved',
                'kategori' => 'laporan',
                'keterangan' => 'Laporan sudah disetujui',
            ]);
            
            RefStatus::create([
                'kode' => 'REJECTED',
                'nama' => 'Rejected',
                'kategori' => 'laporan',
                'keterangan' => 'Laporan ditolak',
            ]);
            
            // Status Pasien
            RefStatus::create([
                'kode' => 'RUTIN',
                'nama' => 'Rutin',
                'kategori' => 'pasien',
                'keterangan' => 'Pasien rutin melakukan pemeriksaan',
            ]);
            
            RefStatus::create([
                'kode' => 'TERKENDALI',
                'nama' => 'Terkendali',
                'kategori' => 'pasien',
                'keterangan' => 'Pasien dengan kondisi terkendali',
            ]);
            
            RefStatus::create([
                'kode' => 'TIDAK_TERKENDALI',
                'nama' => 'Tidak Terkendali',
                'kategori' => 'pasien',
                'keterangan' => 'Pasien dengan kondisi tidak terkendali',
            ]);
            
            // Status Sasaran
            RefStatus::create([
                'kode' => 'ACTIVE',
                'nama' => 'Aktif',
                'kategori' => 'sasaran',
                'keterangan' => 'Sasaran aktif',
            ]);
            
            RefStatus::create([
                'kode' => 'INACTIVE',
                'nama' => 'Tidak Aktif',
                'kategori' => 'sasaran',
                'keterangan' => 'Sasaran tidak aktif',
            ]);
            
            $this->info('Data Referensi Status berhasil ditambahkan.');
        } else {
            $this->info('Data Referensi Status sudah ada, skip seeding.');
        }
    }
}