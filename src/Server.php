<?php

namespace Mbrevda\LogStream;

class Server
{
    /**
     * @var socket server
     */
    private $server;

    /**
     * Callback to pass messages to
     *
     */
    private $callback;

    /**
     * Construct
     *
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Start a streaming log viwer
     */
    public function run($address = '')
    {
        $address = 'tcp://' . $address;
        $this->connect($address);

        for (;;) {
            $stream = stream_socket_accept($this->server, -1);

            if ($stream && $msg = $this->getMessage($stream)) {
                $this->callback($this->normalizeMessage($msg));
                
                unset($msg);
            }
            
        }
    }

    /**
     * Normalize messages recevied as json, including error checking
     *
     * @param string $msg recevied message
     *
     * @return object the messaged, parsed
     */
    private function normalizeMessage($message)
    {
        $message = trim($message);
        $msg = json_decode($message);
        $err = json_last_error_msg();

        // return an error on error or blank message
        if (!$message || $err != 'No error') {
            if (!$message) {
                $err = 'Blank message recevied';
            }

            $msg = new \stdClass;
            $msg->datetime = new \stdClass;
            $msg->datetime->date = time();
            $msg->channel = 'UNKNOWN';
            $msg->level_name = 'UNKNOWN';
            $msg->location = __FILE__ . ':' . __LINE__;
            $msg->message = $err . ': "' . $message . '"';

            return $msg;
        }

        if (isset($msg->context)
            && is_object($msg->context)
            && isset($msg->context->file)
        ) {
            $msg->location = $msg->context->file
                . ':'
                . $msg->context->line;
        } else {
            $msg->location = '';
        }

        return $msg;
    }

    /**
     * Listner
     */
    private function connect($address)
    {
        $this->server = @stream_socket_server(
            $address,
            $errno,
            $errorMessage
        );

        if ($this->server === false) {
            throw new \UnexpectedValueException(
                'Could not bind to socket '. $address . ': ' . $errorMessage
            );
        }

        return true;
    }

    /**
     * Retrive data from a stream
     *
     * @param stream $stream
     *
     * @return string message recevied
     */
    private function getMessage($stream)
    {
        $contents = '';
        while (!feof($stream)) {
            $contents .= fread($stream, 8192);
        }

        return $contents;
    }
}
