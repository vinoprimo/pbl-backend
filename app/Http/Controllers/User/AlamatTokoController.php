<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AlamatToko;
use App\Models\Toko;
use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AlamatTokoController extends Controller
{
    /**
     * Get addresses for authenticated user's store
     * 
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $addresses = AlamatToko::where('id_toko', $toko->id_toko)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $addresses
        ]);
    }

    /**
     * Get a specific store address
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $address = AlamatToko::where('id_alamat_toko', $id)
            ->where('id_toko', $toko->id_toko)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $address
        ]);
    }

    /**
     * Create a new store address
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'nama_pengirim' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'alamat_lengkap' => 'required|string',
            'provinsi' => 'required|string|exists:provinces,id',
            'kota' => 'required|string|exists:regencies,id',
            'kecamatan' => 'required|string|exists:districts,id',
            'kode_pos' => 'required|string|max:10',
            'is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If is_primary is true, unset any existing primary address
        if ($request->input('is_primary', false)) {
            AlamatToko::where('id_toko', $toko->id_toko)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }
        
        $address = new AlamatToko();
        $address->id_toko = $toko->id_toko;
        $address->nama_pengirim = $request->nama_pengirim;
        $address->no_telepon = $request->no_telepon;
        $address->alamat_lengkap = $request->alamat_lengkap;
        $address->provinsi = $request->provinsi;
        $address->kota = $request->kota;
        $address->kecamatan = $request->kecamatan;
        $address->kode_pos = $request->kode_pos;
        $address->is_primary = $request->input('is_primary', false);
        $address->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Store address created successfully',
            'data' => $address
        ], 201);
    }

    /**
     * Update a store address
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $address = AlamatToko::where('id_alamat_toko', $id)
            ->where('id_toko', $toko->id_toko)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'nama_pengirim' => 'string|max:255',
            'no_telepon' => 'string|max:20',
            'alamat_lengkap' => 'string',
            'provinsi' => 'string|exists:provinces,id',
            'kota' => 'string|exists:regencies,id',
            'kecamatan' => 'string|exists:districts,id',
            'kode_pos' => 'string|max:10',
            'is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If is_primary is true, unset any existing primary address
        if ($request->has('is_primary') && $request->is_primary) {
            AlamatToko::where('id_toko', $toko->id_toko)
                ->where('id_alamat_toko', '!=', $id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }
        
        $address->fill($request->only([
            'nama_pengirim', 
            'no_telepon',
            'alamat_lengkap',
            'provinsi',
            'kota',
            'kecamatan',
            'kode_pos',
            'is_primary'
        ]));
        
        $address->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Store address updated successfully',
            'data' => $address
        ]);
    }

    /**
     * Delete a store address
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $address = AlamatToko::where('id_alamat_toko', $id)
            ->where('id_toko', $toko->id_toko)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        $address->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Store address deleted successfully'
        ]);
    }

    /**
     * Set address as primary
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function setPrimary($id)
    {
        $userId = Auth::user()->id_user;
        
        // First check if the user has a store
        $toko = Toko::where('id_user', $userId)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a store'
            ], 404);
        }
        
        $address = AlamatToko::where('id_alamat_toko', $id)
            ->where('id_toko', $toko->id_toko)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        // Unset any existing primary address
        AlamatToko::where('id_toko', $toko->id_toko)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
        
        // Set this address as primary
        $address->is_primary = true;
        $address->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Store address set as primary successfully',
            'data' => $address
        ]);
    }
}
