<?php

namespace App\Http\Controllers;

use App\Models\Inventaris;
use App\Models\Inventaris_Detail;
use App\Services\ImageManager\ImageService;
use App\Services\Notification\NotificationPusher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventarisController extends Controller
{
    public function index(): Response
    {
        $breadcrumb = [
            'list' => ['Home', 'inventaris'],
            'url' => ['home', 'inventaris.data-inventaris.index'],
        ];
        return response()->view('pages.inventaris.data-inventaris.index', [
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Display the specified resource.
     */

    public function show(string $id)
    {
        $inventaris = Inventaris::find($id);

        $breadcrumb = [
            'list' => ['Home', 'Inventaris', 'Detail inventaris'],
            'url' => ['home', 'inventaris.data-inventaris.index', ['inventaris.data-inventaris.show', $id]],
        ];

        return response()->view('pages.inventaris.data-inventaris.show', [
            'inventaris' => $inventaris,
            'breadcrumb' => $breadcrumb,
            'toolbar_id' => $id,
            'active' => 'detail',
            'toolbar_route' => [
                'detail' => route('inventaris.data-inventaris.show', $id),
                'edit' => route('inventaris.data-inventaris.edit', $id),
                'hapus' => route('inventaris.data-inventaris.destroy', $id),
            ],
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $inventaris = Inventaris::find($id);

            $validated = $request->validate([
                'nama_inventaris' => 'required',
                'merk' => 'required',
                'warna' => 'required',
                'jumlah' => 'required|integer',
                'jenis' => 'required',
                'sumber' => 'required',
                'keterangan' => 'required|string|max:255',
            ],[
                'nama_inventaris.required' => 'Nama inventaris wajib diisi',
                'merk.required' => 'Merk inventaris wajib diisi',
                'warna.required' => 'Warna inventaris wajib diisi',
                'jumlah.required' => 'Jumlah inventaris wajib diisi',
                'jumlah.integer' => 'Jumlah inventaris harus berupa angka',
                'jenis.required' => 'Jenis inventaris wajib diisi',
                'sumber.required' => 'Sumber inventaris wajib diisi',
                'keterangan.max' => 'Keterangan inventaris maksimal 255 karakter',
                'keterangan.string' => 'Keterangan inventaris harus berupa string',
                'keterangan.required' => 'Keterangan inventaris wajib diisi',
            ]);


            if ($request->foto_inventaris) {
                $inventaris['foto_inventaris'] = ImageService::uploadFile('public', $request, 'foto_inventaris');
            }

            try {
                $inventaris->update([
                    'nama_inventaris' => $validated['nama_inventaris'],
                    'merk' => $validated['merk'],
                    'warna' => $validated['warna'],
                    'jumlah' => $validated['jumlah'],
                    'jenis' => $validated['jenis'],
                    'sumber' => $validated['sumber'],
                    'foto_inventaris' => $inventaris['foto_inventaris'],
                    'keterangan' => $validated['keterangan'],
                ]);

                NotificationPusher::success('Perubahan berhasil disimpan');
                return redirect()->route('inventaris.data-inventaris.show', $id)->with(['success' => 'Perubahan berhasil disimpan']);
            } catch (\Throwable $th) {
                NotificationPusher::error('Gagal menyimpan perubahan');
                return redirect()->route('inventaris.data-inventaris.show', $id)->with(['error' => 'Gagal menyimpan perubahan']);
            }
        } catch (\Throwable $th) {
            abort(404);
        }
    }

    public function list(): JsonResponse
    {
        try {
            $data = Inventaris::orderBy('updated_at', 'DESC')->get()->map(function ($inventaris) {
                return [
                    'inventaris_id' => $inventaris->inventaris_id,
                    'nama_inventaris' => $inventaris->nama_inventaris,
                    'jumlah' => $inventaris->jumlah,
                    'sumber' => $inventaris->sumber,
                ];
            });

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }

    public function edit(string $id): Response
    {
        $inventaris = Inventaris::find($id);

        $breadcrumb = [
            'list' => ['Home', 'Inventaris', 'Edit inventaris'],
            'url' => ['home', 'inventaris.data-inventaris.index', ['inventaris.data-inventaris.edit', $id]],
        ];

        return response()->view('pages.inventaris.data-inventaris.edit', [
            'breadcrumb' => $breadcrumb,
            'inventaris' => $inventaris,
            'toolbar_id' => $id,
            'active' => 'edit',
            'toolbar_route' => [
                'detail' => route('inventaris.data-inventaris.show', $id),
                'edit' => route('inventaris.data-inventaris.edit', $id),
                'hapus' => route('inventaris.data-inventaris.destroy', $id),
            ],
            'extension' => 'jpg,jpeg,png',
        ]);
    }

    public function create()
    {
        $inventaris = Inventaris::all();

        $breadcrumb = [
            'list' => ['Home', 'Inventaris', 'Tambah inventaris'],
            'url' => ['home', 'inventaris.data-inventaris.index', 'inventaris.data-inventaris.create'],
        ];
        return response()->view('pages.inventaris.data-inventaris.create', [
            'breadcrumb' => $breadcrumb,
            'inventaris' => $inventaris,
            'extension' => 'jpg,jpeg,png',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $pendudukId = Auth::user()->penduduk->penduduk_id;

            $merk = $request->input('merk') ?? '-';
            $warna = $request->input('warna') ?? '-';

            $validated = $request->validate([
                'nama_inventaris' => 'required',
                'jumlah' => 'required|integer',
                'jenis' => 'required',
                'sumber' => 'required',
                'foto_inventaris' => 'required|image',
                'keterangan' => 'required|string|max:255',
            ],[
                'foto_inventaris.required' => 'Foto inventaris wajib diisi',
                'foto_inventaris.image' => 'Foto inventaris harus berupa gambar',
                'nama_inventaris.required' => 'Nama inventaris wajib diisi',
                'jumlah.required' => 'Jumlah inventaris wajib diisi',
                'jumlah.integer' => 'Jumlah inventaris harus berupa angka',
                'jenis.required' => 'Jenis inventaris wajib diisi',
                'sumber.required' => 'Sumber inventaris wajib diisi',
                'keterangan.max' => 'Keterangan inventaris maksimal 255 karakter',
                'keterangan.string' => 'Keterangan inventaris harus berupa string'
            ]);

            $inventaris['foto_inventaris'] = ImageService::uploadFile('public', $request, 'foto_inventaris');
            
            DB::beginTransaction();

            try {
                Inventaris::create([
                    'penduduk_id' => $pendudukId,
                    'nama_inventaris' => $validated['nama_inventaris'],
                    'merk' => $merk,
                    'warna' => $warna,
                    'jumlah' => $validated['jumlah'],
                    'jenis' => $validated['jenis'],
                    'sumber' => $validated['sumber'],
                    'foto_inventaris' => $inventaris['foto_inventaris'],
                    'keterangan' => $validated['keterangan'],
                ]);

                DB::commit();

                NotificationPusher::success('Data berhasil disimpan');
                return redirect()->route('inventaris.data-inventaris.index')->with(['success' => 'Data berhasil disimpan']);
            } catch (\Exception $e) {
                DB::rollback();
                NotificationPusher::error('Gagal menyimpan data: ' . $e->getMessage());
                return redirect()->route('inventaris.data-inventaris.index')->with(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
            }
        } catch (\Throwable $th) {
            abort(404);
        }
    }

    public function destroy(String $id): JsonResponse | RedirectResponse
    {
        try {
            $inventaris = Inventaris::findOrFail($id);

            try {
                DB::beginTransaction();

                $inventaris->delete();

                DB::commit();
                return response()->json([
                    'code' => 200,
                    'message' => 'Data berhasil dihapus',
                    'timestamp' => now(),
                    'redirect' => route('inventaris.index'),
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();

                return response()->json([
                    'code' => 500,
                    'message' => 'Data Inventaris masih ada didalam data Peminjaman',
                    'timestamp' => now(),
                ], 500);
            }
        } catch (\Throwable $th) {
            abort(404);
        }
    }
}
