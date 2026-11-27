<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueOwner\VenueOwnerCourtResource;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\VenueFacility;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * CourtController — SMK 2
 * -------------------------
 * Tugas   : Kelola data Court/Lapangan milik sebuah Venue.
 * Tabel DB: tb_court
 *
 * Metode yang perlu diisi:
 *   - index()  → tampilkan daftar court milik venue
 *   - create() → tampilkan form tambah court baru
 *   - store()  → simpan court baru ke tb_court
 */
class CourtController extends Controller
{
    /**
     * Tampilkan semua court milik venue tertentu.
     * Route: GET /venues/{id}/courts
     */
    public function index($id)
    {
        $venueModel = $this->ownedVenue($id, true);
        $venue = [
            'id' => $venueModel->venue_id,
            'name' => $venueModel->nama_venue,
            'sport' => $venueModel->courts->first()?->sport?->nama_sport ?? 'Padel',
            'address' => $venueModel->alamat,
            'city' => $venueModel->kota ?: 'Jakarta',
            'pic_name' => $venueModel->nama_pic ?: ($venueModel->owner?->nama ?? 'PIC Venue'),
            'pic_phone' => $venueModel->no_whatsapp ?: ($venueModel->owner?->no_hp ?? '-'),
            'operating_hours' => $venueModel->jam_operasional ?: '07:00 - 22:00',
            'hari_buka' => $venueModel->hari_buka ?: 'Setiap Hari (Senin - Minggu)',
            'unavailability_note' => $venueModel->catatan ?: 'Sesuai jadwal ketersediaan lapangan reguler.',
            'catatan' => $venueModel->catatan,
            'image' => $venueModel->main_photo,
            'facilities' => explode(',', $venueModel->fasilitas ?: 'Belum dicatat'),
            'courts' => $venueModel->courts->map(function (Court $court) {
                $type = $court->tipe_court;
                if (empty($type) && ! empty($court->deskripsi)) {
                    if (preg_match('/Tipe:\s*(Indoor|Outdoor|Semi-Indoor)/i', $court->deskripsi, $matches)) {
                        $type = $matches[1];
                    }
                }
                $harga = $court->harga_per_jam;
                if (empty($harga) && ! empty($court->deskripsi)) {
                    if (preg_match('/(?:Rp|IDR)\s*([\d\.,]+)/i', $court->deskripsi, $pMatches)) {
                        $harga = (float) str_replace(['.', ','], '', $pMatches[1]);
                    }
                }

                return [
                    'name' => $court->nama_court,
                    'status' => $court->status_ketersediaan ?? 'Available',
                    'type' => $type ?? 'Tidak ditentukan',
                    'harga_per_jam' => (float) ($harga ?? 0),
                ];
            }),
        ];

        return view('venues.show', compact('venue'));
    }

    /**
     * Tampilkan form tambah court baru untuk venue tertentu.
     * Route: GET /venues/{id}/courts/create
     */
    public function create(Request $request, $id)
    {
        $venue = $this->ownedVenue($id);
        $count = max(1, min(12, (int) $request->query('count', 1)));
        $sport = $request->query('sport', 'Padel');
        $type = in_array($request->query('type'), ['Indoor', 'Outdoor', 'Semi-Indoor'])
            ? $request->query('type')
            : 'Indoor';

        $sports = Sport::all();

        return view('venues.courts.create', compact('venue', 'count', 'sport', 'type', 'sports'));
    }

    /**
     * Simpan court baru ke database (mendukung batch multi-court).
     * Route: POST /venues/{id}/courts
     */
    public function store(Request $request, $id, SupabaseStorageService $storage)
    {
        $venue = $this->ownedVenue($id);

        // Jika dikirim dalam format batch multi-court (courts[])
        if ($request->has('courts') && is_array($request->input('courts'))) {
            $validated = $request->validate([
                'courts' => 'required|array|min:1',
                'courts.*.nama_court' => ['required', 'string', 'max:100', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/'],
                'courts.*.sport_id' => 'nullable',
                'courts.*.sport_name' => 'nullable|string',
                'courts.*.tipe_court' => 'required|string|in:Indoor,Outdoor,Semi-Indoor',
                'courts.*.harga_per_jam' => 'required|numeric|min:0',
                'courts.*.facility_ids' => 'nullable|array',
                'courts.*.facility_ids.*' => 'integer',
                'courts.*.foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            ], [
                'courts.*.nama_court.not_regex' => 'Nama court tidak boleh mengandung tag script atau karakter khusus (< >).',
            ]);

            $padelSportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['padel'])->value('sport_id')
                ?? Sport::value('sport_id');
            $tennisSportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['tennis'])->value('sport_id')
                ?? $padelSportId;

            foreach ($validated['courts'] as $candidate) {
                $this->assertFacilitiesBelongToVenue($candidate['facility_ids'] ?? [], (int) $venue->venue_id);
            }

            $createdCourts = collect();
            foreach ($validated['courts'] as $loopIndex => $c) {
                $targetSportId = $c['sport_id'] ?? null;
                if (! $targetSportId && ! empty($c['sport_name'])) {
                    $targetSportId = strtolower($c['sport_name']) === 'tennis' ? $tennisSportId : $padelSportId;
                }
                if (! $targetSportId) {
                    $targetSportId = $padelSportId;
                }

                $court = Court::create([
                    'venue_id' => $venue->venue_id,
                    'sport_id' => $targetSportId,
                    'nama_court' => strip_tags($c['nama_court']),
                    'status_ketersediaan' => 'Available',
                    'image_url' => $request->hasFile("courts.{$loopIndex}.foto")
                        ? $this->uploadCourtPhoto($request->file("courts.{$loopIndex}.foto"), $storage)
                        : null,
                    'tipe_court' => $c['tipe_court'],
                    'harga_per_jam' => $c['harga_per_jam'],
                    'deskripsi' => "Tipe: {$c['tipe_court']} • Rp ".number_format($c['harga_per_jam']).'/jam',
                ]);
                $this->syncFacilities($court, $c['facility_ids'] ?? [], (int) $venue->venue_id);
                $createdCourts->push($court);
            }

            if ($request->expectsJson()) {
                $createdCourts->each(fn (Court $court) => $court->load(['sport', 'facilities.sport', 'schedules']));

                return VenueOwnerCourtResource::collection($createdCourts)
                    ->response()
                    ->setStatusCode(201);
            }

            return $this->redirectAfterCourtChange($request, (int) $venue->venue_id, count($validated['courts']).' Court berhasil ditambahkan!');
        }

        // Fallback untuk single court
        $validated = $request->validate([
            'nama_court' => ['required', 'string', 'max:100', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/'],
            'tipe_court' => 'required|string|in:Indoor,Outdoor,Semi-Indoor',
            'harga_per_jam' => 'required|numeric|min:0',
            'sport_id' => 'nullable',
            'sport_name' => 'nullable|string',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'integer',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'nama_court.not_regex' => 'Nama court tidak boleh mengandung tag script atau karakter khusus (< >).',
        ]);

        $this->assertFacilitiesBelongToVenue($validated['facility_ids'] ?? [], (int) $venue->venue_id);
        $sportId = $validated['sport_id'] ?? null;
        if (! $sportId && ! empty($validated['sport_name'])) {
            $sportId = Sport::whereRaw('LOWER(nama_sport) = ?', [strtolower($validated['sport_name'])])->value('sport_id');
        }
        if (! $sportId) {
            $sportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['padel'])->value('sport_id') ?? Sport::value('sport_id');
        }

        $court = Court::create([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sportId,
            'nama_court' => strip_tags($validated['nama_court']),
            'status_ketersediaan' => 'Available',
            'image_url' => $request->hasFile('foto') ? $this->uploadCourtPhoto($request->file('foto'), $storage) : null,
            'tipe_court' => $validated['tipe_court'],
            'harga_per_jam' => $validated['harga_per_jam'],
            'deskripsi' => "Tipe: {$validated['tipe_court']} • Rp ".number_format($validated['harga_per_jam']).'/jam',
        ]);
        $this->syncFacilities($court, $validated['facility_ids'] ?? [], (int) $venue->venue_id);

        if ($request->expectsJson()) {
            return (new VenueOwnerCourtResource($court->load(['sport', 'facilities.sport', 'schedules'])))
                ->response()
                ->setStatusCode(201);
        }

        return $this->redirectAfterCourtChange($request, (int) $venue->venue_id, 'Court berhasil ditambahkan!');
    }

    /**
     * Update data court/lapangan di database.
     * Route: PUT /venues/{id}/courts/{courtId}
     */
    public function update(Request $request, $id, $courtId, SupabaseStorageService $storage)
    {
        $venue = $this->ownedVenue($id);
        $court = Court::where('venue_id', $venue->venue_id)->where('court_id', $courtId)->firstOrFail();

        $validated = $request->validate([
            'nama_court' => ['required', 'string', 'max:100', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/'],
            'tipe_court' => 'required|string|in:Indoor,Outdoor,Semi-Indoor',
            'harga_per_jam' => 'required|numeric|min:0',
            'status_ketersediaan' => 'required|string|in:Available,Maintenance,Inactive',
            'sport_id' => 'nullable',
            'sport_name' => 'nullable|string',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'integer',
            'facility_ids_present' => 'nullable|boolean',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'nama_court.required' => 'Nama court wajib diisi.',
            'nama_court.not_regex' => 'Nama court tidak boleh mengandung tag script atau karakter khusus (< >).',
            'tipe_court.required' => 'Tipe court wajib dipilih.',
            'harga_per_jam.required' => 'Harga per jam wajib diisi.',
        ]);

        if (array_key_exists('facility_ids_present', $validated)) {
            $this->assertFacilitiesBelongToVenue($validated['facility_ids'] ?? [], (int) $venue->venue_id);
        }

        $sportId = $validated['sport_id'] ?? null;
        if (! $sportId && ! empty($validated['sport_name'])) {
            $sportId = Sport::whereRaw('LOWER(nama_sport) = ?', [strtolower($validated['sport_name'])])->value('sport_id');
        }
        if (! $sportId) {
            $sportId = $court->sport_id ?: (Sport::whereRaw('LOWER(nama_sport) = ?', ['padel'])->value('sport_id') ?? Sport::value('sport_id'));
        }

        $updates = [
            'nama_court' => strip_tags($validated['nama_court']),
            'sport_id' => $sportId,
            'tipe_court' => $validated['tipe_court'],
            'harga_per_jam' => $validated['harga_per_jam'],
            'status_ketersediaan' => $validated['status_ketersediaan'],
            'deskripsi' => "Tipe: {$validated['tipe_court']} • Rp ".number_format($validated['harga_per_jam']).'/jam',
        ];
        if ($request->hasFile('foto')) {
            $updates['image_url'] = $this->uploadCourtPhoto($request->file('foto'), $storage);
        }
        $court->update($updates);
        if (array_key_exists('facility_ids_present', $validated)) {
            $this->syncFacilities($court, $validated['facility_ids'] ?? [], (int) $venue->venue_id);
        }

        if ($request->expectsJson()) {
            return new VenueOwnerCourtResource($court->load(['sport', 'facilities.sport', 'schedules']));
        }

        return $this->redirectAfterCourtChange($request, (int) $venue->venue_id, "Data {$court->nama_court} berhasil diperbarui!");
    }

    public function destroy(Request $request, $id, $courtId)
    {
        $venue = $this->ownedVenue($id);
        $court = Court::where('venue_id', $venue->venue_id)->where('court_id', $courtId)->firstOrFail();

        // Relational safety check: Jika court terikat pertandingan atau sesi
        if ($court->matches()->exists() || $court->sessions()->exists() || $court->bookings()->exists()) {
            $court->update(['status_ketersediaan' => 'Inactive']);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Lapangan \"{$court->nama_court}\" memiliki riwayat, sehingga statusnya menjadi Inactive.",
                    'deactivated' => true,
                ]);
            }

            return $this->redirectAfterCourtChange($request, (int) $venue->venue_id, "Lapangan \"{$court->nama_court}\" memiliki riwayat pertandingan/sesi/booking, sehingga statusnya diubah menjadi Nonaktif (Inactive) untuk menjaga data.");
        }

        $court->facilities()->detach();
        $court->schedules()->delete();
        $court->slotBlocks()->delete();
        $court->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Lapangan / Court berhasil dihapus.', 'deleted' => true]);
        }

        return $this->redirectAfterCourtChange($request, (int) $venue->venue_id, 'Lapangan / Court berhasil dihapus.');
    }

    private function ownedVenue($id, bool $withCourts = false): Venue
    {
        $query = Venue::query()->where('venue_id', $id);

        if (Auth::user()?->role !== 'admin') {
            $query->where('owner_user_id', Auth::id());
        }

        if ($withCourts) {
            $query->with('courts');
        }

        return $query->firstOrFail();
    }

    private function syncFacilities(Court $court, array $facilityIds, int $venueId): void
    {
        $facilityIds = array_values(array_unique(array_map('intval', $facilityIds)));
        $this->assertFacilitiesBelongToVenue($facilityIds, $venueId);

        $court->facilities()->sync($facilityIds);
    }

    private function assertFacilitiesBelongToVenue(array $facilityIds, int $venueId): void
    {
        $facilityIds = array_values(array_unique(array_map('intval', $facilityIds)));
        if ($facilityIds === []) {
            return;
        }

        $validCount = VenueFacility::query()
            ->where('venue_id', $venueId)
            ->whereIn('facility_id', $facilityIds)
            ->count();
        if ($validCount !== count($facilityIds)) {
            throw ValidationException::withMessages([
                'facility_ids' => 'Pilih fasilitas yang terdaftar pada venue ini.',
            ]);
        }
    }

    private function uploadCourtPhoto(\Illuminate\Http\UploadedFile $photo, SupabaseStorageService $storage): string
    {
        $url = $storage->upload($photo, 'courts');
        if (! $url) {
            throw ValidationException::withMessages(['foto' => 'Foto court gagal disimpan. Silakan coba kembali.']);
        }

        return $url;
    }

    private function redirectAfterCourtChange(Request $request, int $venueId, string $message)
    {
        if (Auth::user()?->role === 'venue_owner' && $request->boolean('owner_dashboard')) {
            return redirect()->route('owner.dashboard', [
                'venue_id' => $venueId,
                'date' => $request->input('date', now()->toDateString()),
            ])->with('success', $message);
        }

        return redirect()->route('venues.show', $venueId)->with('success', $message);
    }
}
