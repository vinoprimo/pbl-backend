<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;

class RegionController extends Controller
{
    /**
     * Get all provinces
     *
     * @return \Illuminate\Http\Response
     */
    public function getProvinces()
    {
        $provinces = Province::all();
        
        return response()->json([
            'status' => 'success',
            'data' => $provinces
        ]);
    }

    /**
     * Get regencies by province
     *
     * @param  string  $provinceId
     * @return \Illuminate\Http\Response
     */
    public function getRegencies($provinceId)
    {
        $province = Province::find($provinceId);
        
        if (!$province) {
            return response()->json([
                'status' => 'error',
                'message' => 'Province not found'
            ], 404);
        }
        
        $regencies = Regency::where('province_id', $provinceId)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $regencies
        ]);
    }

    /**
     * Get districts by regency
     *
     * @param  string  $regencyId
     * @return \Illuminate\Http\Response
     */
    public function getDistricts($regencyId)
    {
        $regency = Regency::find($regencyId);
        
        if (!$regency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regency not found'
            ], 404);
        }
        
        $districts = District::where('regency_id', $regencyId)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $districts
        ]);
    }

    /**
     * Get villages by district
     *
     * @param  string  $districtId
     * @return \Illuminate\Http\Response
     */
    public function getVillages($districtId)
    {
        $district = District::find($districtId);
        
        if (!$district) {
            return response()->json([
                'status' => 'error',
                'message' => 'District not found'
            ], 404);
        }
        
        $villages = Village::where('district_id', $districtId)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $villages
        ]);
    }
}
