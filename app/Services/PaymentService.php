<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Passenger records a payment. It stays "pending" until the driver (cash) or
     * a payment gateway (later phase) confirms it.
     */
    public function create(User $user, Booking $booking, array $data): Payment
    {
        if ($booking->passenger_id !== $user->id) {
            throw new HttpException(403, 'This is not your booking.');
        }
        if ($booking->booking_status === Booking::STATUS_CANCELLED) {
            throw new HttpException(409, 'Cannot pay for a cancelled booking.');
        }

        $outstanding = $this->outstanding($booking);
        if ($data['amount'] > $outstanding + 0.001) {
            throw new HttpException(422, 'Amount exceeds the outstanding balance of '.number_format($outstanding, 2).'.');
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'payment_method' => $data['payment_method'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'amount' => $data['amount'],
            'commission_amount' => round($data['amount'] * config('ride.commission_percent') / 100, 2),
            'refund_amount' => 0,
            'payment_status' => 'pending',
        ]);

        if ($driverId = $booking->driverId()) {
            $this->notifications->notify($driverId, 'Payment Submitted', "Payment of {$payment->amount} submitted for booking #{$booking->id}.", 'payment');
        }

        return $payment;
    }

    /** Driver (or admin) confirms they received the payment. */
    public function confirm(Payment $payment, User $actor): Payment
    {
        $booking = $payment->booking;

        if (! $this->bookings->isDriverOrAdmin($booking, $actor)) {
            throw new HttpException(403, 'Only the driver can confirm a payment.');
        }
        if ($payment->payment_status !== 'pending') {
            throw new HttpException(409, 'This payment is not pending.');
        }

        return DB::transaction(function () use ($payment, $booking) {
            $payment->update(['payment_status' => 'completed', 'payment_date' => now()]);
            $this->bookings->refreshPaymentStatus($booking);
            $this->notifications->notify($payment->user_id, 'Payment Confirmed', "Your payment for booking #{$booking->id} was confirmed.", 'payment');

            return $payment;
        });
    }

    public function outstanding(Booking $booking): float
    {
        $reserved = (float) $booking->payments()->whereIn('payment_status', ['pending', 'completed'])->sum('amount');

        return max((float) $booking->total_amount - $reserved, 0);
    }
}
