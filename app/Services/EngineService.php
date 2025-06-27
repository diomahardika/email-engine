<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class EngineService
{
    // Function untuk mengonsumsi pesan email dari RabbitMQ dan mengirim email
    public function sendEmail(array $data)
    {
        // Validasi data wajib
        if (empty($data) || !is_array($data)) {
            throw new \InvalidArgumentException("Data email tidak valid (null atau bukan array).");
        }
        if (!isset($data['to']) || empty($data['to'])) {
            throw new \InvalidArgumentException("Field 'to' harus diisi.");
        }
        if (!isset($data['priority']) || empty($data['priority'])) {
            throw new \InvalidArgumentException("Field 'priority' harus diisi.");
        }

        $data['attachment'] = is_array($data['attachment'] ?? null) ? $data['attachment'] : [];
        $subject = isset($data['subject']) ? $data['subject'] : '';
        $rawContent = isset($data['content']) ? $data['content'] : '';
        $content = $this->convertHtmlToPlainText($rawContent);

        // Download attachments terlebih dahulu
        $tempFiles = [];
        if (!empty($data['attachment'])) {
            foreach ($data['attachment'] as $attachmentUrl) {
                if ($attachmentUrl === null || $attachmentUrl === '') {
                    continue;
                }
                if (!filter_var($attachmentUrl, FILTER_VALIDATE_URL)) {
                    foreach ($tempFiles as $file) {
                        @unlink($file['path']);
                    }
                    throw new \Exception("Attachment URL tidak valid: $attachmentUrl");
                }

                // Cek tipe konten dengan cURL
                $ch = curl_init($attachmentUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                $response = curl_exec($ch);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $allowedTypes = [
                    'image/jpeg',
                    'image/jpg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                    'application/zip',
                    'application/x-zip-compressed',
                    'application/x-rar-compressed',
                ];

                // Validasi kode HTTP dan tipe konten
                if ($httpCode !== 200 || !in_array($contentType, $allowedTypes)) {
                    foreach ($tempFiles as $file) {
                        @unlink($file['path']);
                    }
                    throw new \Exception("Attachment tidak valid atau bukan file yang diizinkan: $attachmentUrl (Content-Type: $contentType)");
                }

                // Unduh file attachment
                $fileContents = @file_get_contents($attachmentUrl);
                if ($fileContents === false) {
                    foreach ($tempFiles as $file) {
                        @unlink($file['path']);
                    }
                    throw new \Exception("Gagal mengunduh attachment: $attachmentUrl");
                }

                // Simpan file ke direktori sementara
                $tempPath = tempnam(sys_get_temp_dir(), 'mail_attach_');
                file_put_contents($tempPath, $fileContents);
                $tempFiles[] = [
                    'path' => $tempPath,
                    'name' => basename(parse_url($attachmentUrl, PHP_URL_PATH))
                ];
            }
        }

        // Kirim email sebagai teks biasa
        Mail::raw($content, function ($message) use ($data, $subject, $tempFiles) {
            $message->to($data['to'])->subject($subject);

            foreach ($tempFiles as $file) {
                $message->attach($file['path'], ['as' => $file['name']]);
            }
        });

        // Hapus file sementara setelah email terkirim
        foreach ($tempFiles as $file) {
            @unlink($file['path']);
        }
    }

    // Function untuk mengonversi HTML ke teks biasa
    private function convertHtmlToPlainText(string $html): string
    {
        $text = str_replace(["<br>", "<br/>", "<br />"], "\n", $html); // Convert <br> menjadi barisan baru
        $text = preg_replace('/<\/?(h[1-6]|p)>/i', "\n\n", $text); // Convert <h1> hingga <h6> dan <p> menjadi barisan baru
        $text = strip_tags($text); // Hapus tag HTML lainnya
        return trim(preg_replace('/\n\s*\n/', "\n\n", $text)); // Hapus barisan kosong berlebihan
    }
}