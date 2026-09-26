<?php

declare(strict_types=1);

/**
 * Read-only inventory of the shared Aiven schema, used during verification to
 * prove the two applications occupy disjoint table namespaces.
 *
 * Usage: php scripts/verify/table-inventory.php
 */

require dirname( __DIR__, 2 ) . '/backend/vendor/autoload.php';

$app = require dirname( __DIR__, 2 ) . '/backend/bootstrap/app.php';
$app->make( Illuminate\Contracts\Console\Kernel::class )->bootstrap();

$db = config( 'database.connections.' . config( 'database.default' ) . '.database' );

$rows = Illuminate\Support\Facades\DB::select(
	'select table_name as name from information_schema.tables where table_schema = ? order by table_name',
	array( $db )
);

$wp_prefix   = (string) getenv( 'DB_PREFIX' );
$api_prefix  = (string) config( 'database.connections.' . config( 'database.default' ) . '.prefix' );

$wp   = array();
$api  = array();
$other = array();

foreach ( $rows as $row ) {
	$name = (string) $row->name;

	if ( '' !== $wp_prefix && str_starts_with( $name, $wp_prefix ) ) {
		$wp[] = $name;
	} elseif ( '' !== $api_prefix && str_starts_with( $name, $api_prefix ) ) {
		$api[] = $name;
	} else {
		$other[] = $name;
	}
}

printf( "Database: %s%s", $db, PHP_EOL );
printf( "WordPress prefix : %-14s %d tables%s", $wp_prefix, count( $wp ), PHP_EOL );
printf( "API prefix       : %-14s %d tables%s", $api_prefix, count( $api ), PHP_EOL );
printf( "Other            : %-14s %d tables%s", '-', count( $other ), PHP_EOL );

if ( [] !== $other ) {
	printf( "Other tables: %s%s", implode( ', ', $other ), PHP_EOL );
}

printf( "%sDisjoint: %s%s", str_repeat( '-', 40 ), [] === array_intersect( $wp, $api ) ? 'yes' : 'NO', PHP_EOL );
