<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use App\Services\EngineService;
use App\Services\EmailLogService;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Illuminate\Support\Facades\Log;

class EmailEngine extends Command
{
    // Mendefinisikan signature command yang digunakan di terminal
    protected $signature = 'rabbitmq:consume';
    protected $description = 'Mengonsumsi Email dari RabbitMQ dan memroses untuk dikirim';

    private $RabbitMQService;
    private $EngineService;
    private $EmailLogService;

    // Function constructor untuk menginisialisasi service yang diperlukan
    public function __construct(RabbitMQService $RabbitMQService, EngineService $EngineService, EmailLogService $EmailLogService)
    {
        parent::__construct();
        $this->RabbitMQService = $RabbitMQService;
        $this->EngineService = $EngineService;
        $this->EmailLogService = $EmailLogService;
    }

    // Function untuk memulai konsumsi pesan dari RabbitMQ
    public function handle()
    {
        $this->info('Email Engine dimulai...');
        try {
            $this->RabbitMQService->connect();
            $this->RabbitMQService->consume('email_queue_engine_only', [$this, 'processEmail']);
            $this->RabbitMQService->wait();
        } catch (AMQPIOException | AMQPConnectionClosedException $e) {
            $this->error('Tidak dapat terhubung ke RabbitMQ. Hubungi administrator.');
            return 1;
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan internal.');
            return 1;
        }
    }

    // Function untuk memproses pesan email yang diterima dari RabbitMQ
    public function processEmail(AMQPMessage $msg)
    {
        try {
            $data = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!$data || !is_array($data)) {
                $this->error("Data email dari queue kosong atau tidak valid.");
                return;
            }
        } catch (\JsonException $e) {
            $this->error("Malformed JSON: " . $e->getMessage());
            return;
        }

        $to = isset($data['to']) ? $data['to'] : null;
        $priority = isset($data['priority']) ? $data['priority'] : null;

        // Validasi wajib
        if (empty($to) || empty($priority)) {
            $this->error("Field 'to' dan 'priority' wajib diisi.");
            Log::warning("Field 'to' dan/atau 'priority' kosong", $data);
            return;
        }

        // Untuk logika pengiriman email
        try {
            if (!$to) {
                throw new \Exception("Field 'to' tidak ditemukan pada data email.");
            }
            $this->EngineService->sendEmail($data);
            $this->EmailLogService->logEmail($data, 'success', null);
            $this->info("Email sukses terkirim kepada: " . ($data['to'] ?? '-'));
        } catch (\Exception $e) {
            $this->EmailLogService->logEmail($data, 'failed', $e->getMessage());
            $this->error("Gagal mengirim email kepada: " . ($data['to'] ?? '-') . " - " . $e->getMessage());
        }
    }
}