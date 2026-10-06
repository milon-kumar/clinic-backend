Hello {{ $customerName }},

Your {{ $siteName }} booking is confirmed.

Treatment: {{ $treatmentName }}
Clinic: {{ $clinicName }}
Date: {{ $appointmentDate }}
Time: {{ $appointmentTime }}
Reference: #{{ $appointmentId }}
@if (! empty($questionUrl))

Please answer a few questions before your visit:
{{ $questionUrl }}
@endif

Please arrive a few minutes early. If you need to change this appointment, contact the clinic.
