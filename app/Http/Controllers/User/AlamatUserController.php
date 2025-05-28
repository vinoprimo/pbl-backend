<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AlamatUser;
use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AlamatUserController extends Controller
{
    /**
     * Display a listing of user addresses with region information
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $addresses = AlamatUser::where('id_user', $user->id_user)
                ->with(['province', 'regency', 'district', 'village'])
                ->get();
            
            // Return response with properly structured data
            return response()->json([
                'status' => 'success',
                'data' => $addresses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific address
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::user()->id_user;
        $address = AlamatUser::where('id_alamat', $id)
            ->where('id_user', $userId)
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
     * Store a newly created address
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'nama_penerima' => 'required|string|max:255',
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
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // If this is the first address or is set as primary, unset other primary addresses
            if ($request->input('is_primary', false)) {
                AlamatUser::where('id_user', $user->id_user)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
            
            // Create new address
            $address = new AlamatUser();
            $address->id_user = $user->id_user;
            $address->nama_penerima = $request->nama_penerima;
            $address->no_telepon = $request->no_telepon;
            $address->alamat_lengkap = $request->alamat_lengkap;
            $address->provinsi = $request->provinsi;
            $address->kota = $request->kota;
            $address->kecamatan = $request->kecamatan;
            $address->kode_pos = $request->kode_pos;
            
            // If this is the first address, always set it as primary
            $addressCount = AlamatUser::where('id_user', $user->id_user)->count();
            $address->is_primary = $addressCount === 0 ? true : $request->input('is_primary', false);
            
            $address->save();
            
            // Load region data for the response
            $address->load(['province', 'regency', 'district', 'village']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Address added successfully',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an address
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $userId = Auth::user()->id_user;
        $address = AlamatUser::where('id_alamat', $id)
            ->where('id_user', $userId)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'nama_penerima' => 'string|max:255',
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
            AlamatUser::where('id_user', $userId)
                ->where('id_alamat', '!=', $id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }
        
        $address->fill($request->only([
            'nama_penerima', 
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
            'message' => 'Address updated successfully',
            'data' => $address
        ]);
    }

    /**
     * Delete an address
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::user()->id_user;
        $address = AlamatUser::where('id_alamat', $id)
            ->where('id_user', $userId)
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
            'message' => 'Address deleted successfully'
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
        $address = AlamatUser::where('id_alamat', $id)
            ->where('id_user', $userId)
            ->first();
            
        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found or not authorized'
            ], 404);
        }
        
        // Unset any existing primary address
        AlamatUser::where('id_user', $userId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
        
        // Set this address as primary
        $address->is_primary = true;
        $address->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Address set as primary successfully',
            'data' => $address
        ]);
    }
}
