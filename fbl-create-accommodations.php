<?php
/**
 * FBL Bulk Accommodation Creator
 * 
 * WP-CLI command that creates all 15 accommodation types with:
 *   - Category assignment (triggers amenity auto-population)
 *   - Generic description
 *   - Capacity meta
 *   - Standard Boat rate
 *   - Premium Boat rate (as optional service addon via mphb_services)
 *   - fbl_amenities_initialized flag
 * 
 * Usage (run from WordPress root):
 *   wp eval-file fbl-create-accommodations.php --allow-root --skip-plugins=woocommerce
 * 
 * Safe to re-run: skips cabins that already exist by title.
 */

if ( ! defined('ABSPATH') ) {
    echo "Run via WP-CLI: wp eval-file fbl-create-accommodations.php --allow-root\n";
    exit;
}

// ============================================================
// CABIN DEFINITIONS
// ============================================================
// Category term IDs: 454=Buck Cabins, 455=Bingwood Cabins, 456=Outposts
// Season ID for rates: 222470 (Spring — covers the active booking season)
// Premium Boat service ID: 222474

$cabins = [

    // --- BUCK CABINS (Sunday to Sunday, 6 cabins) ---
    [
        'title'       => 'Buck Cabin 1',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 1 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],
    [
        'title'       => 'Buck Cabin 2',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 2 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],
    [
        'title'       => 'Buck Cabin 3',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 3 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],
    [
        'title'       => 'Buck Cabin 4',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 4 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],
    [
        'title'       => 'Buck Cabin 5',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 5 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],
    [
        'title'       => 'Buck Cabin 6',
        'category'    => 454,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Buck Cabin 6 is a fully equipped, wood-heated lakeside cabin at Buck Lake Lodge. Enjoy direct lake access from your private dock, comfortable sleeping arrangements, and all the amenities you need for a memorable wilderness fishing trip. Sunday to Sunday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'sunday',
    ],

    // --- BINGWOOD CABINS (Friday to Friday, 5 cabins) ---
    [
        'title'       => 'Bingwood Cabin 1',
        'category'    => 455,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Bingwood Cabin 1 is a fully equipped, wood-heated lakeside cabin at Bingwood Lodge on Buck Lake. Enjoy your private dock, comfortable accommodations, and direct access to some of the finest fishing in Northern Ontario. Friday to Friday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'friday',
    ],
    [
        'title'       => 'Bingwood Cabin 2',
        'category'    => 455,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Bingwood Cabin 2 is a fully equipped, wood-heated lakeside cabin at Bingwood Lodge on Buck Lake. Enjoy your private dock, comfortable accommodations, and direct access to some of the finest fishing in Northern Ontario. Friday to Friday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'friday',
    ],
    [
        'title'       => 'Bingwood Cabin 3',
        'category'    => 455,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Bingwood Cabin 3 is a fully equipped, wood-heated lakeside cabin at Bingwood Lodge on Buck Lake. Enjoy your private dock, comfortable accommodations, and direct access to some of the finest fishing in Northern Ontario. Friday to Friday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'friday',
    ],
    [
        'title'       => 'Bingwood Cabin 4',
        'category'    => 455,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Bingwood Cabin 4 is a fully equipped, wood-heated lakeside cabin at Bingwood Lodge on Buck Lake. Enjoy your private dock, comfortable accommodations, and direct access to some of the finest fishing in Northern Ontario. Friday to Friday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'friday',
    ],
    [
        'title'       => 'Bingwood Cabin 5',
        'category'    => 455,
        'adults'      => 4,
        'children'    => 2,
        'total'       => 6,
        'description' => 'Bingwood Cabin 5 is a fully equipped, wood-heated lakeside cabin at Bingwood Lodge on Buck Lake. Enjoy your private dock, comfortable accommodations, and direct access to some of the finest fishing in Northern Ontario. Friday to Friday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'friday',
    ],

    // --- OUTPOSTS (fly-in, 4 cabins) ---
    [
        'title'       => 'Buffalo Island Lake Outpost',
        'category'    => 456,
        'adults'      => 4,
        'children'    => 0,
        'total'       => 4,
        'description' => 'Buffalo Island Lake Outpost is a remote fly-in wilderness cabin accessible by float plane from Hornepayne, ON. This self-catering outpost offers exceptional fishing in pristine, untouched waters far from the crowds. A true wilderness experience for the serious angler. Saturday to Saturday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'saturday',
    ],
    [
        'title'       => 'Bayfield Lake Outpost',
        'category'    => 456,
        'adults'      => 4,
        'children'    => 0,
        'total'       => 4,
        'description' => 'Bayfield Lake Outpost is a remote fly-in wilderness cabin accessible by float plane from Hornepayne, ON. This self-catering outpost offers exceptional fishing in pristine, untouched waters far from the crowds. A true wilderness experience for the serious angler. Saturday to Saturday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'saturday',
    ],
    [
        'title'       => 'White Owl Lake Outpost',
        'category'    => 456,
        'adults'      => 4,
        'children'    => 0,
        'total'       => 4,
        'description' => 'White Owl Lake Outpost is a remote fly-in wilderness cabin accessible by float plane from Hornepayne, ON. This self-catering outpost offers exceptional fishing in pristine, untouched waters far from the crowds. A true wilderness experience for the serious angler. Saturday to Saturday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'saturday',
    ],
    [
        'title'       => 'Gourlay Lake Outpost',
        'category'    => 456,
        'adults'      => 4,
        'children'    => 0,
        'total'       => 4,
        'description' => 'Gourlay Lake Outpost is a remote fly-in wilderness cabin accessible by float plane from Hornepayne, ON. This self-catering outpost offers exceptional fishing in pristine, untouched waters far from the crowds. A true wilderness experience for the serious angler. Saturday to Saturday packages.',
        'rate_name'   => 'Standard Boat Package',
        'check_in'    => 'saturday',
    ],
];

// ============================================================
// RATE PRICE STRUCTURE (matches your existing rates exactly)
// season 222470 = Spring (used as the active season template)
// periods: 1-night=$250 deposit, 7-night=$1170/person
// ============================================================
$standard_season_prices = serialize([
    [
        'season' => '222470',
        'price'  => [
            'periods'           => [0 => 1, 1 => 7],
            'prices'            => [0 => 250.0, 1 => 1170.0],
            'base_adults'       => 2,
            'base_children'     => 0,
            'extra_adult_prices'=> [0 => 250.0, 1 => 1170.0],
            'extra_child_prices'=> [0 => '', 1 => ''],
            'enable_variations' => true,
            'variations'        => [],
        ],
    ],
]);

// Premium: $1,470/person, 7-night only, 1 adult base
$premium_season_prices = serialize([
    [
        'season' => '222470',
        'price'  => [
            'periods'           => [0 => 1, 1 => 7],
            'prices'            => [0 => 1470.0, 1 => ''],
            'base_adults'       => 1,
            'base_children'     => 0,
            'extra_adult_prices'=> [0 => 1470.0, 1 => ''],
            'extra_child_prices'=> [0 => 0.0, 1 => ''],
            'enable_variations' => true,
            'variations'        => [],
        ],
    ],
]);

$standard_description = "Well maintained, 14 ft aluminum boat with 15 hp Yamaha, electric start motors, swivel seats, depth finder, landing net, bait bucket, safety kit, and life preservers\r\nRound trip float plane flight from Hornepayne, ON\r\nFully equipped, wood heated cabin with all your housekeeping needs, private dock and mid week cleaning\r\nDaily boat cleaning and refueling with all your gas requirements\r\nFish cleaning facilities with freezer and ice machine\r\nComplimentary shore lunch on Loon Island\r\nUse of canoes, kayak and portage lakes\r\nAll towels and linens supplied\r\nLake map and orientation\r\nExcellent hospitality with personal service";

$premium_description = $standard_description; // same inclusions, upgraded boat

// ============================================================
// AMENITY MAP (from plugin options or fallback)
// ============================================================
$amenity_map_raw = get_option( 'fbl_category_amenity_map', [] );

// Fallback hardcoded map if plugin not yet activated
if ( empty( $amenity_map_raw ) ) {
    $amenity_map_raw = [
        0   => [
            '14 ft aluminum boat with 15hp Yamaha electric start motor',
            'Depth finder, landing net, bait bucket, safety kit & life preservers',
            'Round trip float plane flight from Hornepayne, ON',
            'Daily boat cleaning & refueling — all gas included',
            'Fish cleaning facilities with freezer and ice machine',
            'Complimentary shore lunch on Loon Island',
            'Use of canoes, kayak and portage lakes',
            'All towels and linens supplied',
            'Lake map and orientation',
            'Excellent hospitality with personal service',
        ],
        456 => [
            'Remote fly-in wilderness location',
            'Fully equipped, wood-heated cabin',
            'Private dock',
            'Self-catering housekeeping setup',
            '7-day package: Saturday to Saturday',
        ],
        454 => [
            'Fully equipped, wood-heated cabin',
            'Private dock with mid-week cleaning',
            'Access to Buck Lake Lodge facilities',
            '7-day package: Sunday to Sunday',
        ],
        455 => [
            'Fully equipped, wood-heated cabin',
            'Private dock with mid-week cleaning',
            'Access to Bingwood Lodge facilities',
            '7-day package: Friday to Friday',
        ],
    ];
}

// ============================================================
// HELPER: get or create a facility term
// ============================================================
function fbl_get_or_create_facility( $name ) {
    $term = term_exists( $name, 'mphb_room_type_facility' );
    if ( ! $term ) {
        $term = wp_insert_term( $name, 'mphb_room_type_facility' );
    }
    if ( is_wp_error( $term ) ) return null;
    return (int) ( is_array( $term ) ? $term['term_id'] : $term );
}

// ============================================================
// HELPER: create a rate post linked to a room type
// ============================================================
function fbl_create_rate( $room_type_id, $title, $description, $season_prices ) {
    $rate_id = wp_insert_post([
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_type'   => 'mphb_rate',
    ]);
    if ( is_wp_error( $rate_id ) ) return false;

    update_post_meta( $rate_id, 'mphb_room_type_id',   (string) $room_type_id );
    update_post_meta( $rate_id, 'mphb_description',    $description );
    update_post_meta( $rate_id, 'mphb_season_prices',  $season_prices );

    return $rate_id;
}

// ============================================================
// MAIN: create each cabin
// ============================================================
$created = 0;
$skipped = 0;

WP_CLI::line( '' );
WP_CLI::line( '=== FBL Accommodation Creator ===' );
WP_CLI::line( '' );

foreach ( $cabins as $cabin ) {

    // Skip if already exists
    $existing = get_page_by_title( $cabin['title'], OBJECT, 'mphb_room_type' );
    if ( $existing ) {
        WP_CLI::warning( "Skipping '{$cabin['title']}' — already exists (ID: {$existing->ID})" );
        $skipped++;
        continue;
    }

    // 1. Create the room type post
    $post_id = wp_insert_post([
        'post_title'   => $cabin['title'],
        'post_content' => $cabin['description'],
        'post_status'  => 'draft',
        'post_type'    => 'mphb_room_type',
    ]);

    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( "Failed to create '{$cabin['title']}': " . $post_id->get_error_message(), false );
        continue;
    }

    // 2. Assign category
    wp_set_post_terms( $post_id, [ (int) $cabin['category'] ], 'mphb_room_type_category' );

    // 3. Set capacity meta
    update_post_meta( $post_id, 'mphb_adults_capacity',      $cabin['adults'] );
    update_post_meta( $post_id, 'mphb_children_capacity',    $cabin['children'] );
    update_post_meta( $post_id, 'mphb_total_capacity',       $cabin['total'] );
    update_post_meta( $post_id, 'mphb_base_adults_capacity', $cabin['adults'] );
    update_post_meta( $post_id, 'mphb_base_children_capacity', '' );
    update_post_meta( $post_id, 'mphb_size',  0 );
    update_post_meta( $post_id, 'mphb_view',  'Lakeside view' );
    update_post_meta( $post_id, 'mphb_bed',   '' );
    update_post_meta( $post_id, 'mphb_gallery', '' );

    // 4. Assign Premium Boat as optional service (ID 222474)
    update_post_meta( $post_id, 'mphb_services', serialize( ['222474'] ) );

    // 5. Auto-populate amenities from category map
    $amenity_names = array_merge(
        $amenity_map_raw[0] ?? [],
        $amenity_map_raw[ $cabin['category'] ] ?? []
    );
    $amenity_names = array_unique( array_filter( $amenity_names ) );
    $term_ids = array_filter( array_map( 'fbl_get_or_create_facility', $amenity_names ) );
    if ( $term_ids ) {
        wp_set_post_terms( $post_id, array_values( $term_ids ), 'mphb_room_type_facility' );
    }

    // 6. Set init flag — amenities done, safe from re-population
    update_post_meta( $post_id, 'fbl_amenities_initialized', '1' );

    // 7. Create Standard Boat rate
    $std_title = $cabin['rate_name'] . ' — ' . $cabin['title'];
    $std_rate_id = fbl_create_rate( $post_id, $std_title, $standard_description, $standard_season_prices );

    // 8. Create Premium Boat rate
    $prem_title = 'Premium Boat Package — ' . $cabin['title'];
    $prem_rate_id = fbl_create_rate( $post_id, $prem_title, $premium_description, $premium_season_prices );

    $rate_info = $std_rate_id ? "rates: std={$std_rate_id}" : "rate creation failed";
    if ( $prem_rate_id ) $rate_info .= " prem={$prem_rate_id}";

    WP_CLI::success( "Created '{$cabin['title']}' ID={$post_id} | amenities=" . count($term_ids) . " | {$rate_info}" );
    $created++;
}

WP_CLI::line( '' );
WP_CLI::line( "Done. Created: {$created} | Skipped: {$skipped}" );
WP_CLI::line( '' );
WP_CLI::line( 'Next steps:' );
WP_CLI::line( '  1. Add photos to each cabin in the WordPress admin' );
WP_CLI::line( '  2. Publish each cabin when photos are ready' );
WP_CLI::line( '  3. Run: wp post list --post_type=mphb_room_type --fields=ID,post_title,post_status --format=table --allow-root' );
WP_CLI::line( '     to get all IDs for the [fbl_accommodations] shortcode' );
