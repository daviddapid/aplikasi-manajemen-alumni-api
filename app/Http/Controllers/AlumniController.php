<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseBuilder;
use App\Http\Requests\CreateAlumniRequest;
use App\Http\Requests\UpdateAlumniRequest;
use App\Http\Resources\AlumniResource;
use App\Imports\AlumnisImport;
use App\Models\Alumni;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class AlumniController extends Controller
{
    public function getAll(Request $request): JsonResponse
    {
        $selected_fields = [
            'id',
            'nama',
            'tempat_kerja',
            'jabatan_kerja',
            'tempat_kuliah',
            'prodi_kuliah',
            'tahun_mulai',
            'tahun_lulus'
        ];

        $data = Alumni::query()
            ->when($request->query('search'), function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('tempat_kerja', 'like', "%{$search}%")
                        ->orWhere('tempat_kuliah', 'like', "%{$search}%");
                });
            })
            ->when($request->query('tahun_mulai'), function (Builder $query, $tahun_mulai) {
                $query->where('tahun_mulai', $tahun_mulai);
            })
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->cursorPaginate(10, $selected_fields);

        return ResponseBuilder::success()
            ->data(AlumniResource::collection($data))
            ->pagination($data->nextPageUrl(), $data->previousPageUrl())
            ->build();
    }

    public function getDetail(Request $request): JsonResponse
    {
        $alumni = Alumni::with('jurusan')->find($request->alumni_id);
        if (!$alumni) {
            return ResponseBuilder::fail()
                ->message('Alumni dengan id: ' . $request->alumni_id . ' tidak ada')
                ->build();
        }

        return ResponseBuilder::success()
            ->data(AlumniResource::make($alumni))
            ->build();
    }

    public function checkEmailExist(Request $request): JsonResponse
    {
        $emailExist = Alumni::query()->where('email', $request->input('email'))->first();
        if ($emailExist) {
            return response()->json(true);
        }
        return response()->json(false);
    }

    public function create(Request $request): JsonResponse
    {
        $alumni = Alumni::create($request->all());

        return ResponseBuilder::success()
            ->data($alumni)
            ->message('Sukses menambah data alumni baru')
            ->build();
    }

    public function update(Request $request, string $alumniId): JsonResponse
    {
        $alumni = Alumni::find($alumniId);
        if (!$alumni) {
            return ResponseBuilder::fail()
                ->message('data alumni tidak ditemukan')
                ->build();
        }

        $alumni->update($request->all());

        return ResponseBuilder::success()
            ->message('sukses memperbarui data alumni')
            ->data($alumni)
            ->build();
    }

    public function destroy(Request $request): JsonResponse
    {
        $alumni = Alumni::find($request->alumni_id);
        if (!$alumni) {
            return ResponseBuilder::fail()
                ->message('data alumni tidak ditemukan')
                ->build();
        }

        $alumni->delete();

        return ResponseBuilder::success()
            ->message('data alumni berhasil dihapus')
            ->build();
    }

    public function importExcel()
    {
        try {
            Excel::import(new AlumnisImport, request()->file('alumni_excel'));

            return ResponseBuilder::success()
                ->message('Sukses import excel')
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::fail()
                ->message($th->getMessage())
                ->build();
        }
    }

    public function getChart(Request $request): JsonResponse
    {
        $total_pengangguran = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->whereNull('tempat_kerja')
            ->whereNull('tempat_kuliah')
            ->count();

        $total_kuliah = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->whereNotNull('tempat_kuliah')
            ->whereNull('tempat_kerja')
            ->count();

        $total_kerja = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->whereNotNull('tempat_kerja')
            ->whereNull('tempat_kuliah')
            ->count();

        $total_kuliah_dan_kerja =  Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->whereNotNull('tempat_kerja')
            ->whereNotNull('tempat_kuliah')
            ->count();

        $bar_data = compact('total_pengangguran', 'total_kuliah', 'total_kerja', 'total_kuliah_dan_kerja');

        $pct_tidak_sesuai = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->where('kesesuaian_kerja', false)
            ->orWhere('kesesuaian_kuliah', false)
            ->count();
        $pct_kuliah_sesuai = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->where('kesesuaian_kuliah', true)
            ->count();
        $pct_kerja_sesuai = Alumni::query()
            ->when($request->query('tahun_lulus'), function (Builder $query, $tahun_lulus) {
                $query->where('tahun_lulus', $tahun_lulus);
            })
            ->where('kesesuaian_kerja', true)
            ->count();

        $total_pct = ($pct_tidak_sesuai + $pct_kuliah_sesuai + $pct_kerja_sesuai);
        $pct_tidak_sesuai = round($pct_tidak_sesuai / $total_pct * 100);
        $pct_kuliah_sesuai = round($pct_kuliah_sesuai / $total_pct * 100);
        $pct_kerja_sesuai = round($pct_kerja_sesuai / $total_pct * 100);

        $pie_data = compact('pct_tidak_sesuai', 'pct_kuliah_sesuai', 'pct_kerja_sesuai');

        return ResponseBuilder::success()
            ->data(compact('bar_data', 'pie_data'))
            ->build();
    }
}
