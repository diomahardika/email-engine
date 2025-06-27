<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendEmailRequest;
use App\Http\Requests\ExcelRequest;
use App\Services\EmailQueueService;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Illuminate\Support\Facades\Log;

class EmailQueueController extends Controller
{
    private $EmailQueueService;

    //Constructor untuk menginisialisasi service 
    public function __construct(EmailQueueService $EmailQueueService)
    {
        $this->EmailQueueService = $EmailQueueService;
    }

    //Function untuk mengirim email ke dalam queue RabbitMQ
    public function sendEmails(SendEmailRequest $request)
    {
        $data = $request->json()->all();

        usort($data['mail'], function ($a, $b) {
            $priorityMap = ['low' => 1, 'medium' => 2, 'high' => 3];
            return $priorityMap[$b['priority']] <=> $priorityMap[$a['priority']];
        });

        try {
            $result = $this->EmailQueueService->processAndQueueEmails($data['mail']);
        } catch (AMQPIOException | AMQPConnectionClosedException $e) {
            Log::error('RabbitMQ connection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat terhubung ke RabbitMQ. Pastikan server RabbitMQ aktif.',
            ], 503);
        } catch (\Exception $e) {
            Log::error('Error queuing emails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email masuk kedalam antrian',
            'messages' => $result['messages'],
        ]);
    }

    //Function untuk mengirim email dari file excel ke dalam queue RabbitMQ
    public function sendEmailsFromExcel(ExcelRequest $request)
    {
        $file = $request->file('excel_file');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada file yang diunggah',
            ], 400);
        }

        try {
            $result = $this->EmailQueueService->processEmailsFromExcel($file);
        } catch (AMQPIOException | AMQPConnectionClosedException $e) {
            Log::error('RabbitMQ connection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat terhubung ke RabbitMQ. Pastikan server RabbitMQ aktif.',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal. Tidak dapat terhubung ke RabbitMQ',
            ], 500);
        }

        if (isset($result['error'])) {
            $status = str_contains($result['error'], 'RabbitMQ') ? 503 : 400;
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], $status);
        }

        if (isset($result['validationErrors'])) {
            return response()->json([
                'success' => false,
                'messages' => $result['validationErrors'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email dari file excel masuk kedalam antrian',
            'messages' => $result['messages'],
        ]);
    }
}