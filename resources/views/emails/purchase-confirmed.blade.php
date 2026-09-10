Hello {{ $customerName }},

Thank you for your {{ $siteName }} purchase.

Order: #{{ $orderId }}
Clinic: {{ $clinicName }}
Total: £{{ number_format($total, 2) }}

Your prepaid sessions:
@foreach ($packages as $package)
- {{ $package['name'] }}: {{ $package['sessions'] }} treatment{{ $package['sessions'] === 1 ? '' : 's' }} at {{ $package['clinic'] }}
@endforeach

These sessions stay at the clinic where you bought them. Book your first visit from your account.
