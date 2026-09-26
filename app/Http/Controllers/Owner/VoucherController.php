<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\InternetVoucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $owner_id)
    {
        return InternetVoucher::query()
            ->where('owner_id', $owner_id)
            ->where('phone_number', $request->input('phone_number', false))
            ->where('generated', $request->input('generated', false))
            ->paginate();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $owner_id)
    {
        $input = $request->validate([
            'username' => 'required|string|min:6|max:30|unique:internet_vouchers,username',
            'password' => 'required|string|min:6|max:20',
            'phone_number' => 'required|string|min:9|max:20',
            'zone_id' => 'required|uuid|exists:zones,id',
            'bundle_id' => 'required|uuid|exists:internet_bundle,id',
        ]);

        $data = array_merge($input, [
            'owner_id' => $owner_id,
        ]);

        return InternetVoucher::createOrFail($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(InternetVoucher $internetVoucher)
    {
        $internetVoucher->load(['bundle']);
        return $internetVoucher;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InternetVoucher $internetVoucher)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InternetVoucher $internetVoucher)
    {
        $internetVoucher->deleteOrFail();
        return response()->json(['message' => 'voucher deleted successfully']);
    }
}
