<?php
/**
 * Minimal WP_Error stub for unit tests (no WordPress loaded).
 *
 * Mirrors the parts of the real class the plugin code and the tests use.
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

    public function get_error_data(): mixed {
        return $this->data;
    }

    public function add_data(mixed $data, string $code = ''): void {
        $this->data = $data;
    }

    public function has_errors(): bool {
        return '' !== $this->code;
    }
}
