import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../session/presentation/create_session_page.dart';
import '../domain/court_model.dart';
import '../domain/venue_model.dart';

class VenueBookingFlowPage extends StatefulWidget {
  const VenueBookingFlowPage({super.key, required this.venue, this.authController});

  final VenueModel venue;
  final AuthController? authController;

  @override
  State<VenueBookingFlowPage> createState() => _VenueBookingFlowPageState();
}

class _VenueBookingFlowPageState extends State<VenueBookingFlowPage> {
  final _supabase = Supabase.instance.client;
  int _step = 0;
  CourtModel? _court;
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  DateTime _date = DateTime.now();
  final Set<int> _selectedSlots = {};
  final Set<String> _bookedStarts = {};
  bool _loadingSlots = false;
  bool _acceptTerms = false;
  bool _booking = false;
  String? _error;
  int? _bookingId;
  String? _bookingCode;

  final List<String> _steps = const ['Court', 'Jadwal', 'Rincian', 'Selesai'];

  double get _price => _court?.hargaPerJam ?? 0;
  double get _total => _price * _selectedSlots.length;

  String _money(num value) => NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0).format(value);

  @override
  void initState() {
    super.initState();
    if (widget.venue.courts.isNotEmpty) _court = widget.venue.courts.first;
    _loadSlots();
  }

  Future<void> _loadSlots() async {
    if (_court == null) return;
    setState(() { _loadingSlots = true; _error = null; _selectedSlots.clear(); _bookedStarts.clear(); });
    final date = DateFormat('yyyy-MM-dd').format(_date);
    try {
      final rows = await _supabase
          .from('tb_booking_slot')
          .select('slot_start, tb_booking!inner(booking_status)')
          .eq('court_id', _court!.courtId)
          .eq('slot_date', date)
          .inFilter('tb_booking.booking_status', ['pending', 'confirmed']);
      for (final row in (rows as List)) {
        final raw = row['slot_start'].toString();
        _bookedStarts.add(raw.substring(0, 5));
      }
    } catch (_) {
      _error = 'Gagal memuat ketersediaan. Silakan coba lagi.';
    }
    if (mounted) setState(() => _loadingSlots = false);
  }

  void _selectCourt(CourtModel court) {
    setState(() { _court = court; _selectedSlots.clear(); _step = 1; });
    _loadSlots();
  }

  bool _isPastDate(DateTime d) {
    final today = DateTime.now();
    final a = DateTime(d.year, d.month, d.day);
    final b = DateTime(today.year, today.month, today.day);
    return a.isBefore(b);
  }

  bool _isPastSlot(int hour) {
    final now = DateTime.now();
    final slot = DateTime(_date.year, _date.month, _date.day, hour);
    return slot.isBefore(now);
  }

  void _toggleSlot(int hour) {
    if (_isPastSlot(hour) || _bookedStarts.contains('${hour.toString().padLeft(2, '0')}:00')) return;
    setState(() {
      if (_selectedSlots.contains(hour)) {
        _selectedSlots.remove(hour);
      } else {
        final sorted = {..._selectedSlots, hour}.toList()..sort();
        final contiguous = sorted.asMap().entries.every((e) => e.key == 0 || sorted[e.key] == sorted[e.key - 1] + 1);
        if (!contiguous) {
          _error = 'Pilih jam yang berurutan.';
          return;
        }
        _selectedSlots.add(hour);
      }
      _error = null;
    });
  }

  Future<void> _confirm() async {
    if (!_acceptTerms || _court == null || _selectedSlots.isEmpty) return;
    setState(() { _booking = true; _error = null; });
    final sorted = _selectedSlots.toList()..sort();
    final date = DateFormat('yyyy-MM-dd').format(_date);
    final start = '${sorted.first.toString().padLeft(2, '0')}:00';
    final endHour = sorted.last + 1;
    final end = '${endHour.toString().padLeft(2, '0')}:00';
    final user = widget.authController?.currentUser;
    final userId = user?.userId;
    try {
      if (userId == null) throw Exception('Silakan login untuk melanjutkan booking.');
      final result = await _supabase.rpc('confirm_venue_booking', params: {
        'p_user_id': userId,
        'p_court_id': _court!.courtId,
        'p_booking_date': date,
        'p_start_time': start,
        'p_end_time': end,
        'p_customer_name': user.nama,
        'p_customer_phone': user.noHp ?? '-',
        'p_hourly_price': _price,
      });
      _bookingId = result is num ? result.toInt() : int.tryParse(result.toString());
      final row = await _supabase.from('tb_booking').select('booking_code').eq('booking_id', _bookingId!).maybeSingle();
      _bookingCode = row?['booking_code']?.toString() ?? 'MT-${DateFormat('yyyyMMdd').format(_date)}';
      setState(() { _booking = false; _step = 3; });
    } catch (e) {
      final msg = e.toString().toLowerCase();
      setState(() {
        _booking = false;
        _error = msg.contains('slot_already_booked')
            ? 'Slot baru saja diambil orang lain. Pilih jam lainnya.'
            : 'Gagal mengonfirmasi booking: ${e.toString()}';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final desktop = width >= 900;
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text('Sewa Venue', style: TextStyle(fontWeight: FontWeight.w800, color: context.txtPrimary)),
        backgroundColor: context.bg,
        foregroundColor: context.txtPrimary,
        elevation: 0,
      ),
      body: SafeArea(
        top: false,
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1180),
            child: Column(
              children: [
                _buildStepper(context),
                Expanded(child: desktop ? _buildDesktopBody(context) : _buildMobileBody(context)),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildStepper(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: Row(
        children: List.generate(_steps.length, (i) {
          final active = i <= _step;
          return Expanded(child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(height: 4, decoration: BoxDecoration(color: active ? context.brandColor : context.surfBorder, borderRadius: BorderRadius.circular(2))),
              const SizedBox(height: 8),
              Text(_steps[i], style: TextStyle(fontSize: 11, fontWeight: i == _step ? FontWeight.w800 : FontWeight.w600, color: active ? context.txtPrimary : context.txtDisabled)),
            ])),
            if (i != _steps.length - 1) const SizedBox(width: 8),
          ]));
        }),
      ),
    );
  }

  Widget _buildDesktopBody(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Expanded(child: SingleChildScrollView(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), child: _stepBody(context))),
    if (_step < 3) SizedBox(width: 340, child: SingleChildScrollView(padding: const EdgeInsets.fromLTRB(0, 0, 16, 24), child: _summaryCard(context, sticky: true))),
  ]);

  Widget _buildMobileBody(BuildContext context) => Column(children: [
    Expanded(child: SingleChildScrollView(padding: const EdgeInsets.fromLTRB(16, 0, 16, 16), child: _stepBody(context))),
    if (_step < 3) _summaryBar(context),
  ]);

  Widget _stepBody(BuildContext context) {
    switch (_step) {
      case 0:
        return _courtGrid(context);
      case 1:
        return _scheduleBody(context);
      case 2:
        return _reviewBody(context);
      default:
        return _successBody(context);
    }
  }

  Widget _courtGrid(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Pilih Court', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900, color: context.txtPrimary)),
      const SizedBox(height: 16),
      GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, crossAxisSpacing: 12, mainAxisSpacing: 12, mainAxisExtent: 210),
        itemCount: widget.venue.courts.length,
        itemBuilder: (_, i) {
          final court = widget.venue.courts[i];
          final selected = _court?.courtId == court.courtId;
          return InkWell(onTap: () => _selectCourt(court), borderRadius: BorderRadius.circular(16), child: Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: context.surf, borderRadius: BorderRadius.circular(16), border: Border.all(color: selected ? context.brandColor : context.surfBorder, width: selected ? 2 : 1)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: Container(decoration: BoxDecoration(color: context.surfSec, borderRadius: BorderRadius.circular(12)), child: Center(child: Icon(court.sportId == 2 ? Icons.sports_tennis : Icons.sports_tennis, size: 44, color: context.brandColor)))),
            const SizedBox(height: 12),
            Text(court.namaCourt, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w800, color: context.txtPrimary)),
            const SizedBox(height: 4),
            Text(court.tipeCourt ?? 'Indoor', style: TextStyle(fontSize: 12, color: context.txtSecondary)),
            const SizedBox(height: 8),
            Text('${_money(court.hargaPerJam ?? 0)} / jam', style: TextStyle(fontWeight: FontWeight.w900, color: context.brandColor)),
          ])));
        },
      ),
    ]);
  }

  Widget _scheduleBody(BuildContext context) {
    final today = DateTime.now();
    final tomorrow = today.add(const Duration(days: 1));
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Pilih Tanggal & Jam', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900, color: context.txtPrimary)),
      const SizedBox(height: 16),
      Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: context.surf, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.surfBorder)), child: _calendar(context)),
      const SizedBox(height: 16),
      Row(children: [
        Expanded(child: OutlinedButton(onPressed: () => _setDate(today), child: const Text('Hari ini'))),
        const SizedBox(width: 8),
        Expanded(child: OutlinedButton(onPressed: () => _setDate(tomorrow), child: const Text('Besok'))),
      ]),
      const SizedBox(height: 16),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Jam tersedia', style: TextStyle(fontWeight: FontWeight.w800, color: context.txtPrimary)), Text('${_money(_price)} / jam', style: TextStyle(fontWeight: FontWeight.w800, color: context.brandColor))]),
      const SizedBox(height: 8),
      if (_loadingSlots) const LinearProgressIndicator(minHeight: 3) else _slotGrid(context),
      if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: AppColors.error, fontSize: 12))),
    ]);
  }

  void _setDate(DateTime date) { setState(() { _date = date; _month = DateTime(date.year, date.month); }); _loadSlots(); }

  Widget _calendar(BuildContext context) {
    final first = DateTime(_month.year, _month.month, 1);
    final firstWeekday = first.weekday % 7;
    final days = DateUtils.getDaysInMonth(_month.year, _month.month);
    final cells = firstWeekday + days;
    final rows = (cells / 7).ceil();
    return Column(children: [
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        IconButton(onPressed: () => setState(() => _month = DateTime(_month.year, _month.month - 1)), icon: const Icon(Icons.chevron_left)),
        Text(DateFormat('MMMM yyyy', 'id_ID').format(_month), style: TextStyle(fontWeight: FontWeight.w900, color: context.txtPrimary)),
        IconButton(onPressed: () => setState(() => _month = DateTime(_month.year, _month.month + 1)), icon: const Icon(Icons.chevron_right)),
      ]),
      const SizedBox(height: 8),
      Row(children: ['Ming','Sen','Sel','Rab','Kam','Jum','Sab'].map((d) => Expanded(child: Center(child: Text(d, style: TextStyle(fontSize: 10, color: context.txtSecondary))))).toList()),
      const SizedBox(height: 8),
      for (int r = 0; r < rows; r++) Row(children: List.generate(7, (c) {
        final index = r * 7 + c;
        if (index < firstWeekday) return const Expanded(child: SizedBox(height: 44));
        final day = index - firstWeekday + 1;
        if (day > days) return const Expanded(child: SizedBox(height: 44));
        final d = DateTime(_month.year, _month.month, day);
        final past = _isPastDate(d);
        final selected = d.year == _date.year && d.month == _date.month && d.day == _date.day;
        return Expanded(child: Padding(padding: const EdgeInsets.all(2), child: InkWell(onTap: past ? null : () => _setDate(d), borderRadius: BorderRadius.circular(10), child: Container(height: 44, alignment: Alignment.center, decoration: BoxDecoration(color: selected ? context.brandColor : Colors.transparent, borderRadius: BorderRadius.circular(10)), child: Text('$day', style: TextStyle(fontWeight: selected ? FontWeight.w900 : FontWeight.w600, color: past ? context.txtDisabled : (selected ? Colors.white : context.txtPrimary)))))));
      })),
    ]);
  }

  Widget _slotGrid(BuildContext context) {
    final hours = List.generate(16, (i) => i + 6);
    return GridView.builder(shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, crossAxisSpacing: 8, mainAxisSpacing: 8, mainAxisExtent: 64), itemCount: hours.length, itemBuilder: (_, i) {
      final hour = hours[i];
      final booked = _bookedStarts.contains('${hour.toString().padLeft(2, '0')}:00');
      final past = _isPastSlot(hour);
      final disabled = booked || past;
      final selected = _selectedSlots.contains(hour);
      final label = booked ? 'Sudah dibooking' : (past ? 'Sudah lewat' : (selected ? 'Dipilih' : 'Tersedia'));
      return InkWell(onTap: disabled ? null : () => _toggleSlot(hour), borderRadius: BorderRadius.circular(12), child: Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8), alignment: Alignment.center, decoration: BoxDecoration(color: selected ? context.brandColor : (disabled ? context.surfSec : context.surf), borderRadius: BorderRadius.circular(12), border: Border.all(color: selected ? context.brandColor : context.surfBorder)), child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Text('${hour.toString().padLeft(2, '0')}:00-${(hour + 1).toString().padLeft(2, '0')}:00', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, decoration: booked ? TextDecoration.lineThrough : null, color: selected ? Colors.white : (disabled ? context.txtDisabled : context.txtPrimary))), Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 9, color: selected ? Colors.white : (disabled ? context.txtDisabled : context.txtSecondary)))])));
    });
  }

  Widget _reviewBody(BuildContext context) {
    final sorted = _selectedSlots.toList()..sort();
    final end = sorted.isEmpty ? '-' : '${(sorted.last + 1).toString().padLeft(2, '0')}:00';
    final start = sorted.isEmpty ? '-' : '${sorted.first.toString().padLeft(2, '0')}:00';
    return Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: context.surf, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.surfBorder)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Rincian Booking', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: context.txtPrimary)),
      const SizedBox(height: 16),
      _reviewRow(context, 'Venue', widget.venue.namaVenue), _reviewRow(context, 'Court', _court?.namaCourt ?? '-'), _reviewRow(context, 'Tanggal', DateFormat('EEEE, d MMMM yyyy', 'id_ID').format(_date)), _reviewRow(context, 'Jam', '$start - $end'), _reviewRow(context, 'Durasi', '${sorted.length} jam'),
      const Divider(height: 32),
      _reviewRow(context, 'Harga per jam', _money(_price)),
      _reviewRow(context, '$_money(_price) x ${sorted.length} jam', _money(_total)),
      const SizedBox(height: 16),
      Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: context.softLime, borderRadius: BorderRadius.circular(12)), child: Row(children: [Icon(Icons.payments_outlined, color: context.brandColor), const SizedBox(width: 12), Expanded(child: Text('Bayar di venue', style: TextStyle(fontWeight: FontWeight.w800, color: context.txtPrimary)))])),
      CheckboxListTile(contentPadding: EdgeInsets.zero, value: _acceptTerms, onChanged: (v) => setState(() => _acceptTerms = v ?? false), title: const Text('Saya setuju dengan syarat booking venue.', style: TextStyle(fontSize: 12)), controlAffinity: ListTileControlAffinity.leading),
      if (_error != null) Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12)),
      const SizedBox(height: 8),
      SizedBox(width: double.infinity, height: 48, child: ElevatedButton(onPressed: !_acceptTerms || _booking ? null : _confirm, style: ElevatedButton.styleFrom(backgroundColor: context.brandColor, foregroundColor: Colors.white), child: _booking ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Konfirmasi Booking', style: TextStyle(fontWeight: FontWeight.w900)))),
    ]));
  }

  Widget _reviewRow(BuildContext context, String label, String value) => Padding(padding: const EdgeInsets.symmetric(vertical: 5), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: Text(label, style: TextStyle(color: context.txtSecondary, fontSize: 12))), Text(value, textAlign: TextAlign.right, style: TextStyle(fontWeight: FontWeight.w800, color: context.txtPrimary, fontSize: 12))]));

  Widget _successBody(BuildContext context) => Center(child: Container(width: double.infinity, constraints: const BoxConstraints(maxWidth: 520), padding: const EdgeInsets.all(24), decoration: BoxDecoration(color: context.surf, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.surfBorder)), child: Column(mainAxisSize: MainAxisSize.min, children: [
    Container(width: 72, height: 72, decoration: BoxDecoration(color: context.softLime, shape: BoxShape.circle), child: Icon(Icons.check_rounded, size: 40, color: context.brandColor)),
    const SizedBox(height: 16), Text('Booking Terkonfirmasi', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: context.txtPrimary)),
    const SizedBox(height: 8), Text(_bookingCode ?? '-', style: TextStyle(fontWeight: FontWeight.w800, color: context.txtSecondary)),
    const SizedBox(height: 24),
    SizedBox(width: double.infinity, height: 48, child: ElevatedButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CreateSessionPage(authController: widget.authController, initialVenueId: widget.venue.venueId))), style: ElevatedButton.styleFrom(backgroundColor: context.brandColor, foregroundColor: Colors.white), child: const Text('Buat Game', style: TextStyle(fontWeight: FontWeight.w900)))),
    const SizedBox(height: 8), TextButton(onPressed: () => Navigator.pop(context), child: const Text('Lihat Booking Saya')),
  ])));

  Widget _summaryCard(BuildContext context, {bool sticky = false}) => Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: context.surf, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.surfBorder)), child: _summaryContent(context));

  Widget _summaryBar(BuildContext context) => Container(padding: EdgeInsets.fromLTRB(16, 10, 16, 10 + MediaQuery.viewPaddingOf(context).bottom), decoration: BoxDecoration(color: context.surf, border: Border(top: BorderSide(color: context.surfBorder))), child: _summaryContent(context));

  Widget _summaryContent(BuildContext context) {
    final sorted = _selectedSlots.toList()..sort();
    final time = sorted.isEmpty ? 'Belum dipilih' : '${sorted.first.toString().padLeft(2, '0')}:00 - ${(sorted.last + 1).toString().padLeft(2, '0')}:00';
    final canContinue = _step == 1 ? sorted.isNotEmpty : _step == 2;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
      Text('Ringkasan', style: TextStyle(fontWeight: FontWeight.w900, color: context.txtPrimary)),
      const SizedBox(height: 12),
      _reviewRow(context, 'Tanggal', DateFormat('d MMM yyyy', 'id_ID').format(_date)),
      _reviewRow(context, 'Jam', time),
      _reviewRow(context, 'Durasi', '${sorted.length} jam'),
      const SizedBox(height: 8),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Total', style: TextStyle(color: context.txtSecondary)), Text(_money(_total), style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: context.brandColor))]),
      const SizedBox(height: 12),
      SizedBox(width: double.infinity, height: 48, child: ElevatedButton(onPressed: canContinue ? () => setState(() => _step = _step == 1 ? 2 : _step) : null, style: ElevatedButton.styleFrom(backgroundColor: context.brandColor, foregroundColor: Colors.white), child: Text(_step == 2 ? 'Konfirmasi' : 'Lanjut', style: const TextStyle(fontWeight: FontWeight.w900)))),
    ]);
  }
}
