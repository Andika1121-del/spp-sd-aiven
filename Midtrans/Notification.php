<?php

namespace Midtrans;

/**
 * Read raw notification data from Midtrans post request
 */
class Notification
{
    private $response;

    public function __construct($inputSource = null)
    {
        $local_raw_input = !empty($inputSource) ? $inputSource : file_get_contents('php://input');

        if (empty($local_raw_input)) {
            throw new \Exception('Notification payload is empty.');
        }

        $this->response = json_decode($local_raw_input);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
        }
    }

    public function __get($name)
    {
        if (isset($this->response->$name)) {
            return $this->response->$name;
        }
        return null;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
