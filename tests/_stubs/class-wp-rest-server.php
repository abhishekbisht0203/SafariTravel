<?php
/**
 * Minimal WP_REST_Server stub.
 *
 * Only the HTTP verb constants are needed by unit tests; the real class is part
 * of WordPress and is never loaded here.
 */
class WP_REST_Server {
    public const READABLE  = 'GET';
    public const CREATABLE = 'POST';
    public const EDITABLE  = 'POST, PUT, PATCH';
    public const DELETABLE = 'DELETE';
    public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
}
