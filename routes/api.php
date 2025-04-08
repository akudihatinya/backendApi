<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PasienController;
use App\Http\Controllers\API\PemeriksaanController;
use App\Http\Controllers\API\LaporanController;
use App\Http\Controllers\API\SasaranController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ExportController;
use App\Http\Controllers\API\PuskesmasController;
use App\Http\Controllers\API\DinasController;
use App\Http\Controllers\API\TahunProgramController;
use App\Http\Controllers\API\AdminUserController;
use App\Http\Controllers\API\RefJenisKelaminController;
use App\Http\Controllers\API\RefJenisProgramController;
use App\Http\Controllers\API\RefStatusController;
use App\Http\Controllers\API\PencapaianController;
use App\Http\Controllers\API\RekapController;
use App\Http\Controllers\API\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Profile management
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    
    // Pasien routes
    Route::apiResource('pasien', PasienController::class);
    Route::get('/pasien/{id}/analisis', [PasienController::class, 'analisisStatus']);
    Route::get('/pasien/search', [PasienController::class, 'search']); // Search by name, NIK, BPJS
    
    // Pemeriksaan routes
    Route::apiResource('pemeriksaan', PemeriksaanController::class);
    Route::get('/pemeriksaan/pasien/{pasien_id}', [PemeriksaanController::class, 'getByPasien']); // Get all examinations for a patient
    
    // Laporan routes
    Route::apiResource('laporan', LaporanController::class);
    Route::post('/laporan/{id}/submit', [LaporanController::class, 'submit']);
    Route::get('/laporan/bulan/{bulan}/tahun/{tahun_program_id}', [LaporanController::class, 'getByMonth']);
    Route::get('/laporan/template', [LaporanController::class, 'generateTemplate']);
    
    // Laporan approval (Dinas only)
    Route::middleware('role:admin,dinas')->group(function () {
        Route::post('/laporan/{id}/approve', [LaporanController::class, 'approve']);
        Route::post('/laporan/{id}/reject', [LaporanController::class, 'reject']);
    });
    
    // Sasaran routes
    Route::apiResource('sasaran', SasaranController::class);
    Route::get('/sasaran/puskesmas', [SasaranController::class, 'sasaranByPuskesmas']);
    Route::get('/sasaran/template', [SasaranController::class, 'generateTemplate']);
    
    // Pencapaian routes
    Route::get('/pencapaian/puskesmas/{puskesmas_id}', [PencapaianController::class, 'getByPuskesmas']);
    Route::get('/pencapaian/bulan/{bulan}/tahun/{tahun_program_id}', [PencapaianController::class, 'getByMonth']);
    Route::get('/pencapaian/grafik', [PencapaianController::class, 'getChartData']);
    
    // Rekap reports
    Route::get('/rekap/dinas/{tahun_program_id}', [RekapController::class, 'getByDinas']);
    Route::get('/rekap/puskesmas/{puskesmas_id}/tahun/{tahun_program_id}', [RekapController::class, 'getByPuskesmas']);
    Route::get('/rekap/program/{jenis_program_id}/tahun/{tahun_program_id}', [RekapController::class, 'getByProgram']);
    
    // Dashboard routes
    Route::get('/dashboard/dinas', [DashboardController::class, 'dinasDashboard']);
    Route::get('/dashboard/puskesmas', [DashboardController::class, 'puskesmasDashboard']);
    Route::get('/dashboard/statistik', [DashboardController::class, 'getStatistics']);
    
    // Export routes (untuk download Excel)
    Route::get('/export/pasien', [ExportController::class, 'exportPasien']);
    Route::get('/export/pemeriksaan', [ExportController::class, 'exportPemeriksaan']);
    Route::get('/export/laporan-bulanan', [ExportController::class, 'exportLaporanBulanan']);
    Route::get('/export/rekap-tahunan', [ExportController::class, 'exportRekapTahunan']);
    
    // Master data routes
    Route::apiResource('puskesmas', PuskesmasController::class);
    Route::apiResource('dinas', DinasController::class);
    
    Route::apiResource('tahun-program', TahunProgramController::class);
    Route::put('/tahun-program/{id}/activate', [TahunProgramController::class, 'activate']);
    Route::get('/tahun-program/active', [TahunProgramController::class, 'getActive']);
    
    // Reference data routes
    Route::prefix('referensi')->group(function() {
        Route::apiResource('jenis-kelamin', RefJenisKelaminController::class);
        Route::apiResource('jenis-program', RefJenisProgramController::class);
        Route::apiResource('status', RefStatusController::class);
        Route::get('/status/kategori/{kategori}', [RefStatusController::class, 'getByKategori']);
    });
    
    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // User management
        Route::apiResource('users', AdminUserController::class);
        Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::put('/users/{id}/change-role', [AdminUserController::class, 'changeRole']);
        
        // System routes
        Route::post('/generate-reports', [AdminUserController::class, 'generateReports']); // Generate reports manually
        Route::post('/archive-data', [AdminUserController::class, 'archiveData']); // Archive data manually
        Route::get('/system-logs', [AdminUserController::class, 'systemLogs']); // View system logs
    });
    
    // App information
    Route::get('/app-info', function() {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'akudihatinya',
                'version' => '1.0.0',
                'description' => 'Aplikasi Diabetes Melitus dan Hipertensi Terlayani',
                'developed_by' => 'Dinas Kesehatan'
            ]
        ]);
    });
});