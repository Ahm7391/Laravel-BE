<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockupBookingData;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastServiceController extends Controller
{
    /**
     * Send mockup booking records within the specified date range to Python pipeline.
     *
     * Expected Request Payload:
     * {
     *     "customer_id": integer,
     *     "start_date": timestamp|string,
     *     "finish_date": timestamp|string
     * }
     *
     * Returns a list of dictionaries:
     * [
     *     {
     *         "customer_id": int,
     *         "booking_date": "Y-m-d H:i:s",
     *         "check_in": "Y-m-d H:i:s",
     *         "check_out": "Y-m-d H:i:s",
     *         "net_amount_stay": int,
     *         "ota": int,
     *         "is_confirmed": bool,
     *         "room_type_id": int
     *     },
     *     ...
     * ]
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendBack(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id');
        $rawStartDate = $request->input('start_date', $request->input('day_start', $request->input('date_start')));
        $rawFinishDate = $request->input('finish_date', $request->input('day_end', $request->input('date_end')));

        $startDate = $this->parseTimestamp($rawStartDate, false);
        $finishDate = $this->parseTimestamp($rawFinishDate, true);

        $query = MockupBookingData::query();

        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', (int) $customerId);
        }

        if ($startDate && $finishDate) {
            $query->whereBetween('booking_date', [$startDate, $finishDate]);
        } elseif ($startDate) {
            $query->where('booking_date', '>=', $startDate);
        } elseif ($finishDate) {
            $query->where('booking_date', '<=', $finishDate);
        }

        $records = $query->orderBy('booking_date', 'asc')->get();

        $data = $records->map(function ($booking) {
            return [
                'customer_id' => (int) $booking->customer_id,
                'booking_date' => $booking->booking_date ? Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s') : null,
                'check_in' => $booking->check_in ? Carbon::parse($booking->check_in)->format('Y-m-d H:i:s') : null,
                'check_out' => $booking->check_out ? Carbon::parse($booking->check_out)->format('Y-m-d H:i:s') : null,
                'net_amount_stay' => (int) $booking->net_amount_stay,
                'ota' => (int) $booking->ota,
                'is_confirmed' => filter_var($booking->is_confirmed, FILTER_VALIDATE_BOOLEAN),
                'room_type_id' => (int) $booking->room_type_id,
            ];
        })->values()->all();

        return response()->json($data);
    }

    /**
     * Parse timestamp or date string into a Carbon instance.
     *
     * @param  mixed  $value
     * @param  bool  $isEnd
     * @return \Carbon\Carbon|null
     */
    private function parseTimestamp($value, bool $isEnd = false): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $num = (int) $value;
                // Handle millisecond epoch if > 10 digits
                if (strlen((string) $num) > 10) {
                    $num = (int) ($num / 1000);
                }
                return Carbon::createFromTimestamp($num);
            }

            $date = Carbon::parse($value);

            // If format was date-only (e.g. '2026-09-10'), adjust boundaries
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
                $date = $isEnd ? $date->endOfDay() : $date->startOfDay();
            }

            return $date;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
