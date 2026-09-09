<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsResult;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    /**
     * Display the analytics dashboard page and job request history.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $customerId = 1; // Locked to customer_id = 1 for simulation
        $history = AnalyticsResult::orderBy('id', 'desc')->paginate(15);

        return view('analytics.dashboard', compact('customerId', 'history'));
    }

    /**
     * Submit an analytics calculation request to the Python FastAPI pipeline.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function requestAnalytics(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'day_start' => 'required|date_format:Y-m-d',
            'day_end' => 'required|date_format:Y-m-d|after_or_equal:day_start',
        ]);

        $customerId = 1; // Enforce locked customer_id = 1
        $dayStart = $validated['day_start'];
        $dayEnd = $validated['day_end'];

        $pipelineBaseUrl = rtrim(env('PYTHON_PIPELINE_URL', 'http://127.0.0.1:8000'), '/');
        $pipelineEndpoint = $pipelineBaseUrl . '/receive-data';

        $payload = [
            'customer_id' => (string) $customerId,
            'day_start' => (string) $dayStart,
            'day_end' => (string) $dayEnd,
        ];

        try {
            // Send request to Python pipeline
            $response = Http::timeout(5)->asJson()->post($pipelineEndpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $keyId = (string) ($data['key_id'] ?? ('JOB_' . time()));
                $statusCode = (int) ($data['status_code'] ?? AnalyticsResult::STATUS_IN_PROGRESS);
                $statusMessage = (string) ($data['status_message'] ?? 'Transfer is done.');

                AnalyticsResult::create([
                    'job_id_key' => $keyId,
                    'customer_id' => $customerId,
                    'status' => $statusCode,
                    'message' => $statusMessage,
                    'date_request' => Carbon::now(),
                ]);

                return redirect()->route('analytics.dashboard')->with('success', "Request sent successfully to Python pipeline. Job ID: {$keyId}");
            }

            // If Python pipeline returned a non-200 response
            $errorMsg = "Pipeline error (HTTP {$response->status()}): " . ($response->json('detail') ?? $response->body() ?? 'Unknown response');
            
            AnalyticsResult::create([
                'job_id_key' => 'JOB_ERR_' . time(),
                'customer_id' => $customerId,
                'status' => AnalyticsResult::STATUS_FAULT,
                'message' => substr($errorMsg, 0, 250),
                'date_request' => Carbon::now(),
            ]);

            return redirect()->route('analytics.dashboard')->with('warning', $errorMsg);
        } catch (\Throwable $e) {
            Log::warning("Failed to connect to Python pipeline at {$pipelineEndpoint}: " . $e->getMessage());

            // Connection refused or offline
            $errorMsg = "FastAPI pipeline offline at {$pipelineEndpoint}. Request recorded locally.";
            $fallbackKeyId = 'JOB_SIM_' . time();

            AnalyticsResult::create([
                'job_id_key' => $fallbackKeyId,
                'customer_id' => $customerId,
                'status' => AnalyticsResult::STATUS_IN_PROGRESS,
                'message' => 'Simulated: ' . $errorMsg,
                'date_request' => Carbon::now(),
            ]);

            return redirect()->route('analytics.dashboard')->with('info', "Job {$fallbackKeyId} created. Note: Python FastAPI endpoint ({$pipelineEndpoint}) was not reachable, so initial state was queued locally.");
        }
    }

    /**
     * Handle asynchronous callback from Python pipeline to update job status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatusFromPipeline(Request $request): JsonResponse
    {
        $keyId = $request->input('key_id', $request->input('job_id_key'));
        $statusCode = $request->input('status_code', $request->input('status'));
        $message = $request->input('status_message', $request->input('message', 'Status updated by Python pipeline.'));

        if (!$keyId || $statusCode === null) {
            return response()->json([
                'success' => false,
                'message' => 'Missing key_id or status_code.',
            ], 422);
        }

        $record = AnalyticsResult::where('job_id_key', (string) $keyId)->latest()->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => "Job with key_id '{$keyId}' not found.",
            ], 404);
        }

        $record->update([
            'status' => (int) $statusCode,
            'message' => (string) $message,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Job {$keyId} status updated successfully.",
            'data' => [
                'job_id_key' => $record->job_id_key,
                'status' => $record->status,
                'status_label' => $record->status_label,
                'message' => $record->message,
            ],
        ]);
    }
}
