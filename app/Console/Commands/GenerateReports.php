<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\TahunProgram;
use App\Models\Puskesmas;
use App\Models\RefJenisProgram;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\RefStatus;
use App\Models\SasaranPuskesmas;
use App\Models\PencapaianBulanan;
use App\Traits\AnalisisHTDM;
use Carbon\Carbon;

class GenerateReports extends Command
{
    use AnalisisHTDM;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-reports {month? : Month number (1-12), defaults to previous month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly achievement reports for all puskesmas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to generate reports...');
        
        // Get active year program
        $tahunProgram = TahunProgram::where('is_active', true)->first();
        
        if (!$tahunProgram) {
            $this->error('No active year program found!');
            return 1;
        }
        
        // Get month argument or use previous month
        $month = $this->argument('month');
        if (!$month) {
            $month = Carbon::now()->subMonth()->month;
        }
        
        $this->info("Generating reports for month: $month, year program: {$tahunProgram->nama}");
        
        // Get all puskesmas
        $puskesmasList = Puskesmas::all();
        
        // Get all jenis program
        $jenisPrograms = RefJenisProgram::all();
        
        // Generate pencapaian for each puskesmas and program
        $bar = $this->output->createProgressBar(count($puskesmasList) * count($jenisPrograms));
        $bar->start();
        
        foreach ($puskesmasList as $puskesmas) {
            foreach ($jenisPrograms as $jenisProgram) {
                // Calculate achievements
                $this->generatePencapaian($tahunProgram->id, $puskesmas->id, $jenisProgram->id, $month);
                $bar->advance();
            }
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Reports generation completed!');
        
        return 0;
    }
    
    /**
     * Generate achievement data for a puskesmas and program
     */
    private function generatePencapaian($tahunProgramId, $puskesmasId, $jenisProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::find($jenisProgramId);
        
        if (!$jenisProgram) {
            return;
        }
        
        // Get total patients for this program
        $totalPasien = Pasien::where('puskesmas_id', $puskesmasId)
            ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jenisProgramId) {
                $q->where('tahun_program_id', $tahunProgramId)
                  ->whereHas('pemeriksaanStatus', function($sq) use($jenisProgramId) {
                      $sq->where('jenis_program_id', $jenisProgramId);
                  })
                  ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
            })->count();
        
        // Get controlled patients for HT or DM
        $terkendaliCount = 0;
        $rutinCount = 0;
        
        if ($jenisProgram->kode === 'HT') {
            // Get pasien HT terkendali
            $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
            
            if ($statusTerkendali) {
                $terkendaliCount = Pasien::where('puskesmas_id', $puskesmasId)
                    ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jenisProgramId, $statusTerkendali) {
                        $q->where('tahun_program_id', $tahunProgramId)
                          ->whereHas('pemeriksaanStatus', function($sq) use($jenisProgramId, $statusTerkendali) {
                              $sq->where('jenis_program_id', $jenisProgramId)
                                 ->where('status_id', $statusTerkendali->id);
                          })
                          ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
                    })->count();
            }
            
            // Calculate HT rutin pasien
            $pasienList = Pasien::where('puskesmas_id', $puskesmasId)
                ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $jenisProgramId) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->whereHas('pemeriksaanStatus', function($sq) use($jenisProgramId) {
                          $sq->where('jenis_program_id', $jenisProgramId);
                      });
                })->get();
                
            foreach ($pasienList as $pasien) {
                $rutin = $this->analisisRutin($pasien->id, $tahunProgramId, $jenisProgramId, $bulan);
                if ($rutin) {
                    $rutinCount++;
                }
            }
        } elseif ($jenisProgram->kode === 'DM') {
            // Get pasien DM terkendali
            $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
            
            if ($statusTerkendali) {
                $terkendaliCount = Pasien::where('puskesmas_id', $puskesmasId)
                    ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jenisProgramId, $statusTerkendali) {
                        $q->where('tahun_program_id', $tahunProgramId)
                          ->whereHas('pemeriksaanStatus', function($sq) use($jenisProgramId, $statusTerkendali) {
                              $sq->where('jenis_program_id', $jenisProgramId)
                                 ->where('status_id', $statusTerkendali->id);
                          })
                          ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
                    })->count();
            }
            
            // Calculate DM rutin pasien
            $pasienList = Pasien::where('puskesmas_id', $puskesmasId)
                ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $jenisProgramId) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->whereHas('pemeriksaanStatus', function($sq) use($jenisProgramId) {
                          $sq->where('jenis_program_id', $jenisProgramId);
                      });
                })->get();
                
            foreach ($pasienList as $pasien) {
                $rutin = $this->analisisRutin($pasien->id, $tahunProgramId, $jenisProgramId, $bulan);
                if ($rutin) {
                    $rutinCount++;
                }
            }
        }
        
        // Save total patient achievement
        $this->savePencapaian($tahunProgramId, $puskesmasId, $jenisProgramId, $bulan, 'total_pasien', $totalPasien);
        
        // Save controlled patient achievement
        $this->savePencapaian($tahunProgramId, $puskesmasId, $jenisProgramId, $bulan, 'terkendali', $terkendaliCount);
        
        // Save rutin patient achievement
        $this->savePencapaian($tahunProgramId, $puskesmasId, $jenisProgramId, $bulan, 'rutin', $rutinCount);
    }
    
    /**
     * Save achievement data
     */
    private function savePencapaian($tahunProgramId, $puskesmasId, $jenisProgramId, $bulan, $parameter, $nilai)
    {
        // Check if achievement already exists
        $pencapaian = PencapaianBulanan::where('tahun_program_id', $tahunProgramId)
            ->where('puskesmas_id', $puskesmasId)
            ->where('jenis_program_id', $jenisProgramId)
            ->where('bulan', $bulan)
            ->where('parameter', $parameter)
            ->first();
            
        if ($pencapaian) {
            // Update if exists
            $pencapaian->update([
                'nilai' => $nilai
            ]);
        } else {
            // Create new if not exists
            PencapaianBulanan::create([
                'tahun_program_id' => $tahunProgramId,
                'puskesmas_id' => $puskesmasId,
                'jenis_program_id' => $jenisProgramId,
                'bulan' => $bulan,
                'parameter' => $parameter,
                'nilai' => $nilai
            ]);
        }
    }
}