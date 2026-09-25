<?php
/**
 * Minimal WP_Error stub for unit tests (no WordPress loaded).
 */
class WP_Error {
    public string $code;
    public string $message;
    public mixed $data;

    public function __construct(string $code = '', string $message = '', mixed $data = '') {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}
