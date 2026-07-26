<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <h2>Reservation received</h2>

    <p>Hi {{ $reservation->guest_name }},</p>

    <p>We've received your reservation. Details below:</p>

    <table cellpadding="6" style="border-collapse: collapse;">
        <tr><td><strong>Booking reference</strong></td><td>{{ $reservation->ota_reservation_id }}</td></tr>
        <tr><td><strong>Property</strong></td><td>{{ $reservation->property_id }}</td></tr>
        <tr><td><strong>Room type</strong></td><td>{{ $reservation->room_type }}</td></tr>
        <tr><td><strong>Check-in</strong></td><td>{{ $reservation->check_in->toDateString() }}</td></tr>
        <tr><td><strong>Check-out</strong></td><td>{{ $reservation->check_out->toDateString() }}</td></tr>
        <tr><td><strong>Total</strong></td><td>{{ number_format((float) $reservation->total_amount, 2) }} {{ $reservation->currency }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ $reservation->status }}</td></tr>
    </table>

    <p>Thank you.</p>
</body>
</html>
