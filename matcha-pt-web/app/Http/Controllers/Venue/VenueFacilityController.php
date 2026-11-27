<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueOwner\VenueOwnerFacilityResource;
use App\Models\Venue;
use App\Models\VenueFacility;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class VenueFacilityController extends Controller
{
    public function store(Request $request, int $venueId, SupabaseStorageService $storage)
    {
        $venue = $this->ownedVenue($venueId);
        $validated = $this->validated($request);
        $photo = $request->hasFile('foto') ? $storage->upload($request->file('foto'), 'venue-facilities') : null;
        if ($request->hasFile('foto') && ! $photo) {
            throw ValidationException::withMessages(['foto' => 'Foto fasilitas gagal disimpan. Silakan coba kembali.']);
        }

        $facility = VenueFacility::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_fasilitas' => $validated['nama_fasilitas'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'foto' => $photo,
            'sport_id' => $validated['sport_id'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return (new VenueOwnerFacilityResource($facility->load('sport')))
                ->response()
                ->setStatusCode(201);
        }

        return $this->redirectToVenue($venue->venue_id)->with('success', 'Fasilitas berhasil ditambahkan.');
    }

    public function update(Request $request, int $venueId, int $facilityId, SupabaseStorageService $storage)
    {
        $venue = $this->ownedVenue($venueId);
        $facility = VenueFacility::query()
            ->where('venue_id', $venue->venue_id)
            ->where('facility_id', $facilityId)
            ->firstOrFail();
        $validated = $this->validated($request);

        if ($request->hasFile('foto')) {
            $photo = $storage->upload($request->file('foto'), 'venue-facilities');
            if (! $photo) {
                throw ValidationException::withMessages(['foto' => 'Foto fasilitas gagal disimpan. Silakan coba kembali.']);
            }
            $facility->foto = $photo;
        }

        $facility->fill([
            'nama_fasilitas' => $validated['nama_fasilitas'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'sport_id' => $validated['sport_id'] ?? null,
        ])->save();

        if ($request->expectsJson()) {
            return new VenueOwnerFacilityResource($facility->load('sport'));
        }

        return $this->redirectToVenue($venue->venue_id)->with('success', 'Informasi fasilitas diperbarui.');
    }

    public function destroy(Request $request, int $venueId, int $facilityId)
    {
        $venue = $this->ownedVenue($venueId);
        $facility = VenueFacility::query()
            ->where('venue_id', $venue->venue_id)
            ->where('facility_id', $facilityId)
            ->firstOrFail();
        $facility->courts()->detach();
        $facility->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Fasilitas berhasil dihapus.']);
        }

        return $this->redirectToVenue($venue->venue_id)->with('success', 'Fasilitas berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_fasilitas' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'sport_id' => ['nullable', 'integer', 'exists:tb_sport,sport_id'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);
    }

    private function ownedVenue(int $venueId): Venue
    {
        abort_unless(Auth::user()?->role === 'venue_owner', 403);

        return Venue::query()
            ->where('venue_id', $venueId)
            ->where('owner_user_id', Auth::id())
            ->firstOrFail();
    }

    private function redirectToVenue(int $venueId)
    {
        return redirect()->route('owner.dashboard', [
            'venue_id' => $venueId,
            'section' => 'venue-profile',
        ]);
    }
}
