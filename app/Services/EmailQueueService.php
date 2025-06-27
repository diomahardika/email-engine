<?php

namespace App\Services;

use PhpAmqpLib\Message\AMQPMessage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Services\RabbitMQService;

class EmailQueueService
{
  private $RabbitMQService;

  //Fungsi untuk menginisialisasi service
  public function __construct(RabbitMQService $RabbitMQService)
  {
    $this->RabbitMQService = $RabbitMQService;
  }

  // Map prioritas string ke nilai numerik
  private $priorityMap = [
    'low' => 1,
    'medium' => 2,
    'high' => 3,
  ];

  //Function untuk memparsing file excel menjadi array
  private function parseExcel($file): array
  {
    $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
      public function array(array $array)
      {
        return $array;
      }
    }, $file);

    return $data[0] ?? [];
  }

  //Function untuk memvalidasi setiap baris data email dari file excel
  private function validateRow(array $row, int $index): array
  {
    $priorityList = array_keys($this->priorityMap);

    $processedRow = [
      'to' => trim($row[0] ?? null),
      'content' => $row[1] ?? '',
      'subject' => $row[2] ?? '',
      'priority' => strtolower(trim($row[3] ?? '')),
      'attachment' => isset($row[4]) ? array_map('trim', explode(',', $row[4])) : [],
    ];

    $errors = [];
    if (empty($processedRow['to'])) {
      $errors[] = 'Email penerima ("to") wajib diisi';
    } elseif (!filter_var($processedRow['to'], FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Format email tidak valid untuk kolom "to"';
    }
    if (empty($processedRow['priority']) || !in_array($processedRow['priority'], $priorityList, true)) {
      $errors[] = 'Prioritas wajib diisi dan harus salah satu dari: low, medium, high';
    }
    if (!empty($processedRow['attachment'])) {
      foreach ($processedRow['attachment'] as $url) {
        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
          $errors[] = 'Setiap attachment harus berupa URL yang valid.';
          break;
        }
      }
    }

    return [
      'row' => $index + 1,
      'data' => $processedRow,
      'errors' => $errors,
    ];
  }

  //Function untuk memparsing file excel menjadi array
  public function processAndQueueEmails(array $emails)
  {
    $channel = $this->RabbitMQService->connect();

    $priorityMap = [
      'low' => 1,
      'medium' => 2,
      'high' => 3,
    ];

    $messages = [];

    foreach ($emails as $mail) {
      if (empty($mail['to']) || empty($mail['priority'])) {
        Log::warning('Missing "to" or "priority" field.', $mail);
        continue;
      }

      try {
        $priority = $priorityMap[$mail['priority']] ?? 2;
        $msg = new AMQPMessage(
          json_encode($mail),
          [
            'delivery_mode' => 2,
            'priority' => $priority,
          ]
        );
        $channel->basic_publish($msg, 'email_exchange_engine_only', 'email_engine');

        $messages[] = $mail;
      } catch (\Exception $e) {
        Log::error('Failed to queue email: ' . $e->getMessage(), $mail);
        continue;
      }
    }

    return ['messages' => $messages];
  }


  //Function untuk memproses email dari file excel
  public function processEmailsFromExcel($file): array
  {
    $rows = $this->parseExcel($file);
    if (empty($rows)) {
      return ['error' => 'File Excel tidak valid atau kosong'];
    }

    $messages = [];
    $validationErrors = [];

    foreach ($rows as $index => $row) {
      // Skip header
      if ($index === 0 && strcasecmp($row[0] ?? '', 'to') === 0) {
        continue;
      }
      // Skip row kosong
      if (empty(array_filter($row, fn($value) => trim($value) !== ''))) {
        continue;
      }

      $result = $this->validateRow($row, $index);
      if ($result['errors']) {
        $validationErrors[] = [
          'row' => $result['row'],
          'errors' => $result['errors'],
        ];
        continue;
      }
      $messages[] = $result['data'];
    }

    if ($validationErrors) {
      return ['validationErrors' => $validationErrors];
    }

    if (empty($messages)) {
      return ['error' => 'Tidak ada data email yang valid ditemukan dalam file Excel'];
    }

    // Urutkan berdasarkan prioritas
    usort($messages, function ($a, $b) {
      return $this->priorityMap[$b['priority']] <=> $this->priorityMap[$a['priority']];
    });

    // Kirim email ke RabbitMQ
    $result = $this->processAndQueueEmails($messages);

    if (isset($result['error'])) {
      return ['error' => $result['error']];
    }

    // Return messages with id (already set in processAndQueueEmails)
    return ['messages' => $result['messages']];
  }
}