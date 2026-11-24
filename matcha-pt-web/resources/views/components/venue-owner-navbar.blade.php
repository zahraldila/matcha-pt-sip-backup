<header class="sticky top-0 z-50 border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('owner.dashboard') }}" class="flex shrink-0 items-center gap-2.5">
            <img src="{{ asset('images/logo.svg') }}" alt="Matcha" class="h-9 w-9 rounded-lg">
            <span class="leading-tight">
                <span class="block text-xs font-black tracking-wide">MATCHA</span>
                <span class="block text-[9px] font-bold tracking-widest text-[#063B00]">MATCH ARENA</span>
            </span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex">
            <a href="{{ route('owner.dashboard') }}" class="rounded-lg bg-[#063B00] px-3 py-2 text-[11px] font-bold text-white">Dashboard</a>
            <a href="{{ route('owner.dashboard', ['venue_id' => request('venue_id'), 'date' => request('date')]).'#booking-calendar' }}" class="rounded-lg px-3 py-2 text-[11px] font-medium text-slate-600 hover:bg-slate-50">Kalender Booking</a>
            <a href="{{ route('owner.dashboard', ['venue_id' => request('venue_id'), 'date' => request('date')]).'#courts' }}" class="rounded-lg px-3 py-2 text-[11px] font-medium text-slate-600 hover:bg-slate-50">Court Saya</a>
            <a href="{{ route('owner.dashboard', ['venue_id' => request('venue_id')]).'#venue-profile' }}" class="rounded-lg px-3 py-2 text-[11px] font-medium text-slate-600 hover:bg-slate-50">Profil Venue</a>
        </nav>

        <div class="flex items-center gap-3">
            <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[#063B00] px-3 py-2 text-[10px] font-bold text-white hover:bg-[#042a00]">
                <i class="fa-solid fa-plus text-[#A8E63A]"></i><span>Tambah Venue</span>
            </a>
            <a href="{{ route('player.profile') }}" class="hidden items-center gap-2 border-l border-slate-200 pl-3 sm:flex">
                <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-[#063B00]/30 bg-[#EBF8D8] text-xs font-bold text-[#063B00]">
                    @if(Auth::user()->foto)
                        <img src="{{ Auth::user()->foto }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                    @endif
                </span>
                <span class="max-w-28 leading-tight">
                    <span class="block truncate text-[11px] font-bold">{{ Auth::user()->nama }}</span>
                    <span class="block text-[9px] text-[#063B00]">Venue Owner</span>
                </span>
            </a>
        </div>
    </div>
</header>
