<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\YearlyTargetRequest;
use App\Http\Resources\YearlyTargetResource;
use App\Models\YearlyTarget;
use Illuminate\Http\Request;

class YearlyTargetController extends Controller
{
    public function index(Request $request)
    {
        $query = YearlyTarget::with('puskesmas');
        
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        
        if ($request->has('disease_type')) {
            $query->where('disease_type', $request->disease_type);
        }
        
        if ($request->has('puskesmas_id')) {
            $query->where('puskesmas_id', $request->puskesmas_id);
        }
        
        $targets = $query->get();
        
        return response()->json([
            'targets' => YearlyTargetResource::collection($targets),
        ]);
    }
    
    public function store(YearlyTargetRequest $request)
    {
        $target = YearlyTarget::updateOrCreate(
            [
                'puskesmas_id' => $request->puskesmas_id,
                'disease_type' => $request->disease_type,
                'year' => $request->year,
            ],
            [
                'target_count' => $request->target_count,
            ]
        );
        
        return response()->json([
            'message' => 'Sasaran tahunan berhasil disimpan',
            'target' => new YearlyTargetResource($target),
        ]);
    }
    
    public function show(YearlyTarget $target)
    {
        return response()->json([
            'target' => new YearlyTargetResource($target),
        ]);
    }
    
    public function update(YearlyTargetRequest $request, YearlyTarget $target)
    {
        $target->update([
            'target_count' => $request->target_count,
        ]);
        
        return response()->json([
            'message' => 'Sasaran tahunan berhasil diupdate',
            'target' => new YearlyTargetResource($target),
        ]);
    }
    
    public function destroy(YearlyTarget $target)
    {
        $target->delete();
        
        return response()->json([
            'message' => 'Sasaran tahunan berhasil dihapus',
        ]);
    }
}
