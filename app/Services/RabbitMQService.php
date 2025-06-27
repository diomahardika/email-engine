<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQService
{
    private $connection;
    private $channel;

    public function __construct()
    {
        
    }

    //Untuk koneksi ke RabbitMQ
    public function connect()
    {
        if (!$this->connection) {
            $this->connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
            $this->channel = $this->connection->channel();
            $this->initializeEmailQueue();
        }
        return $this->channel;
    }

    //Untuk menginisialisasi exchange dan queue di RabbitMQ
    public function initializeEmailQueue()
    {
        // Deklarasi exchange dengan tipe 'direct'
        $this->channel->exchange_declare('email_exchange_engine_only', 'direct', false, true, false);

        // Deklarasi queue dengan nama 'email_queue' dan mendukung prioritas
        $this->channel->queue_declare('email_queue_engine_only', false, true, false, false, false, [
            'x-max-priority' => ['I', 3] // Jangkauan prioritas 0-3
        ]);

        // Bind queue ke exchange dengan routing key 'email'
        $this->channel->queue_bind('email_queue_engine_only', 'email_exchange_engine_only', 'email_engine');
    }

    //Untuk mengonsumsi pesan dari RabbitMQ
    public function consume(string $queue, callable $callback)
    {
        $this->channel->basic_consume($queue, '', false, false, false, false, function ($msg) use ($callback) {
            $msg->ack();  // Acknowledge first to avoid duplicates
            $callback($msg);  // Process the email after acknowledgment
        });
    }


    //Sebagai mekanisme agar kode menunggu jika ada message yang masuk di queue
    public function wait()
    {
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    //Untuk menutup koneksi ke RabbitMQ
    public function close()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}