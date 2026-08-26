<?php
/* =========================================================
   FBL BROCHURE GENERATOR
   Assembles the brochure PDF that fbl_brochure_pdf_id points at
   (see inc/rate-estimator.php) from three ingredients:
     - the site logo
     - editable copy (fbl_brochure_copy): masthead eyebrow/title/sub,
       intro paragraph, the three highlight stats, the species line,
       the CTA block, the closing line, and the footer contact block
       (including a QR code generated locally at build time - see
       fbl_brochure_qr_data_uri() - since dompdf has remote fetching
       disabled and can't pull one from an external QR API)
     - a photo album pulled from the "Brochure_FBL" FileBird folder,
       masonry-arranged, omitted entirely when that folder is empty

   This file only builds the PDF (fbl_generate_brochure_pdf()) and
   returns a filesystem path to it. Nothing here saves that path to
   fbl_brochure_pdf_id or hooks up an admin "Generate" button yet -
   that wiring is a separate, deliberate step once the output has
   been reviewed.

   IMPORTANT for that future wiring, so it doesn't leak: the path
   returned here comes from wp_tempnam(), which on this install
   resolves to /tmp/ (get_temp_dir()) - it is NEVER cleaned up by
   this file, that's the caller's job, and there is no caller yet.
   Confirmed by observation: 15 orphaned fbl-brochure-* files were
   found in /tmp/ from manual testing, plus 2 older ones sitting
   directly in wp-content/uploads/ from an earlier session, all
   uncleaned. When the "Generate" button is built:
     - unlink() this returned tmp path once it's been copied
       somewhere permanent (into the attachment file, or wherever),
       on every call, not just on success paths;
     - regenerating should REPLACE the existing fbl_brochure_pdf_id
       attachment's file in place + refresh its metadata via
       wp_update_attachment_metadata() - not call wp_insert_attachment()
       again, which would pile up a new Media Library row per click.
       Only the very first-ever generate should insert a new
       attachment; every regenerate after that reuses the same ID.
   ========================================================= */

require_once get_stylesheet_directory() . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

/* ---------------------------------------------------------
   Logo - resolved by filename rather than a hardcoded
   attachment ID, re-checked on every call so a replaced/
   deleted logo can't silently point at a dead file (same
   philosophy as fbl_get_brochure_pdf_id() in rate-estimator.php).
   --------------------------------------------------------- */
function fbl_get_brochure_logo_id() {
    global $wpdb;

    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'attachment'
           AND post_mime_type = 'image/png'
           AND guid LIKE %s
         ORDER BY ID ASC LIMIT 1",
        '%final-logo.png'
    ));

    if (!$id || !get_attached_file($id) || !file_exists(get_attached_file($id))) {
        return 0;
    }

    return $id;
}

/* ---------------------------------------------------------
   Brochure copy - a standalone option, same reasoning as
   fbl_brochure_pdf_id: not a rate figure, not part of
   fblRateData, needs its own sanitize path. Defaults are the
   copy already reviewed for the brochure. 'lede' and
   'species_line' allow a handful of inline formatting tags
   (see fbl_brochure_kses()) so a word or two can stay bold;
   everything else is plain text, escaped with esc_html() on
   output.
   --------------------------------------------------------- */
function fbl_get_brochure_copy() {
    $defaults = array(
        'eyebrow'        => 'Fly-In Fishing  •  Northern Ontario',
        'title'          => 'FISH BUCK LAKE',
        'header_sub'     => 'Wilderness Lodges & Outposts  —  Hornepayne, Ontario',
        'lede'           => 'Deep in the boreal forests of Northern Ontario, six pristine lakes — reachable only by floatplane — hold some of the finest trophy fishing left in North America. <strong>Fish Buck Lake</strong> has welcomed anglers to authentic Canadian wilderness for nearly two decades, and our disciplined catch-and-release program keeps every lake fishing as strong today as it did on day one.',
        'stat1_num'      => '11',
        'stat1_label'    => 'Cabins',
        'stat2_num'      => '6',
        'stat2_label'    => 'Wilderness Lakes',
        'stat3_num'      => '20',
        'stat3_label'    => 'Years of Stewardship',
        'species_line'   => 'Trophy <b>Northern Pike</b>  •  hard-fighting <b>Walleye</b>  •  fast-action <b>jumbo Perch</b>',
        'cta_headline'   => 'See rates, availability & every outpost in detail',
        'cta_sub'        => 'Full trip planning, live pricing, and instant booking — right on our site',
        'cta_url'        => 'fishbucklake.com',
        'closing'        => 'Nine cabins on Buck Lake — two remote outposts beyond it. Choose your wilderness.',
        'footer_business' => 'Buck Lake Wilderness Lodges & Outposts',
        'footer_address'  => 'Hornepayne, Ontario, Canada',
        'footer_phone'    => '(705) 534-1991',
        'footer_email'    => 'info@fishbucklake.com',
        'footer_qr_url'     => 'https://www.fishbucklake.com/',
        'footer_qr_caption' => 'Scan to visit',
    );

    $saved = get_option('fbl_brochure_copy', array());
    if (!is_array($saved)) {
        $saved = array();
    }

    return array_merge($defaults, array_intersect_key($saved, $defaults));
}

/**
 * Sanitizer for the two copy fields ('lede', 'species_line') that are
 * allowed a couple of inline formatting tags. Deliberately narrow -
 * just enough to keep a word bold, nothing that could break the
 * brochure's fixed single-page layout (no links, images, block tags).
 */
function fbl_brochure_kses($html) {
    return wp_kses($html, array(
        'strong' => array(),
        'b'      => array(),
    ));
}

/* ---------------------------------------------------------
   Footer QR code - generated locally at build time (rather
   than pulled from an external QR API) since dompdf's chroot
   + setIsRemoteEnabled(false) block outbound fetches. Returns
   a base64 PNG data URI dompdf can embed directly in <img src>,
   or '' if $url is empty so the footer can skip the image.
   --------------------------------------------------------- */
function fbl_brochure_qr_data_uri($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $options = new QROptions(array(
        'outputType'    => QROutputInterface::GDIMAGE_PNG,
        'scale'         => 8,
        'outputBase64'  => true, // QRCode::render() returns a data: URI
        'quietzoneSize' => 2,
    ));

    return (new QRCode($options))->render($url);
}

/* ---------------------------------------------------------
   Photo album - packs a FileBird folder's images into $columns
   masonry columns using shortest-column-first placement (each
   image goes into whichever column is currently shortest, height
   estimated from its real aspect ratio at a fixed column width).
   Same visual pattern as [fbl_gallery]'s masonry view; a
   PHP-computed layout rather than that view's CSS `columns:`
   because Dompdf doesn't support CSS multi-column layout.
   --------------------------------------------------------- */
function fbl_brochure_masonry_columns($ids, $columns = 3) {
    $columns   = max(1, (int) $columns);
    $col_items = array_fill(0, $columns, array());
    $col_height = array_fill(0, $columns, 0.0);
    $col_width  = 100 / $columns; // percent, just for the height estimate

    foreach ($ids as $id) {
        $meta = wp_get_attachment_metadata($id);
        $w = !empty($meta['width'])  ? (float) $meta['width']  : 4;
        $h = !empty($meta['height']) ? (float) $meta['height'] : 3;
        $estimated_height = $col_width * ($h / $w);

        $shortest = array_search(min($col_height), $col_height, true);
        $col_items[$shortest][]  = $id;
        $col_height[$shortest]  += $estimated_height;
    }

    return $col_items;
}

/**
 * Local file path for an attachment at a given size, falling back
 * through progressively larger registered sizes, then the original,
 * so a missing intermediate size never drops an image silently.
 */
function fbl_brochure_image_path($id, $preferred_size = 'medium_large') {
    $upload_dir = wp_upload_dir();

    foreach (array($preferred_size, 'large', 'medium', 'full') as $size) {
        $data = image_get_intermediate_size($id, $size);
        if ($data && !empty($data['path'])) {
            $path = trailingslashit($upload_dir['basedir']) . $data['path'];
            if (file_exists($path)) {
                return $path;
            }
        }
    }

    $full = get_attached_file($id);
    return ($full && file_exists($full)) ? $full : '';
}

/* ---------------------------------------------------------
   Build the HTML, render it, write the PDF to a temp file.
   Returns the filesystem path, or '' on failure (e.g. no
   dompdf, no writable temp location).
   --------------------------------------------------------- */
function fbl_generate_brochure_pdf() {
    $copy    = fbl_get_brochure_copy();
    $logo_id = fbl_get_brochure_logo_id();
    $qr_uri  = fbl_brochure_qr_data_uri($copy['footer_qr_url']);

    $photo_ids = fbl_gallery_get_ids('Brochure_FBL'); // null = folder missing, [] = empty
    $has_album = is_array($photo_ids) && !empty($photo_ids);

    // Values for the running header/footer (drawn per-page below via the
    // dompdf PHP-callback canvas, not normal HTML flow) - var_export'd so
    // they can be embedded as safe PHP string literals inside that inline
    // <script type="text/php"> block's source.
    $running_title_literal   = var_export($copy['title'], true);
    $running_contact_literal = var_export(
        $copy['footer_business'] . '   •   ' . $copy['footer_phone'] . '   •   ' . $copy['footer_email'],
        true
    );

    ob_start();
    ?>
    <html>
    <head>
    <style>
        @page { size: letter; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            color: #1c2b3a;
            background: #f4f1e8;
        }

        /* Header band */
        .fbl-b-header {
            background: #16283f;
            color: #f4f1e8;
            padding: 0.55in 0.7in 0.5in 0.7in;
            text-align: center;
        }
        .fbl-b-logo { margin-bottom: 12pt; }
        .fbl-b-logo img { width: 70pt; height: auto; }
        .fbl-b-eyebrow {
            letter-spacing: 0.25em; font-size: 12px; color: #c9a24a;
            font-weight: bold; text-transform: uppercase; margin-bottom: 10px;
        }
        .fbl-b-title { margin: 0; font-size: 40px; font-weight: bold; letter-spacing: 0.02em; }
        .fbl-b-header-sub { margin-top: 10px; font-size: 15px; color: #dcd6c4; }

        .fbl-b-body { padding: 0.5in 0.75in 0 0.75in; }
        .fbl-b-lede { font-size: 17px; line-height: 1.55; color: #16283f; margin: 0 0 0.28in 0; }
        .fbl-b-lede strong, .fbl-b-lede b { color: #16283f; }

        .fbl-b-highlights { width: 100%; border-collapse: separate; border-spacing: 0.12in 0; margin-bottom: 0.24in; }
        .fbl-b-highlight {
            width: 33.33%; background: #ffffff; border: 1px solid #e0d9c4;
            border-top: 3px solid #c9a24a; padding: 0.14in 0.18in; text-align: center;
        }
        .fbl-b-highlight .num { font-size: 26px; font-weight: bold; color: #16283f; display: block; }
        .fbl-b-highlight .cap {
            font-size: 11px; color: #5a5344; text-transform: uppercase;
            letter-spacing: 0.06em; margin-top: 4px; display: block;
        }

        .fbl-b-species { text-align: center; font-size: 14px; color: #16283f; letter-spacing: 0.03em; margin-bottom: 0.24in; }
        .fbl-b-species b, .fbl-b-species strong { color: #a8792e; }

        .fbl-b-cta {
            background: #16283f; color: #f4f1e8; text-align: center;
            padding: 0.32in 0.6in; margin: 0 0.75in; border-radius: 3px;
        }
        .fbl-b-cta .headline { font-size: 20px; font-weight: bold; margin-bottom: 6px; }
        .fbl-b-cta .sub2 { font-size: 13px; color: #cfc9b6; margin-bottom: 0.2in; }
        .fbl-b-cta .url { font-size: 24px; font-weight: bold; color: #f4d98a; letter-spacing: 0.02em; }

        .fbl-b-closing {
            text-align: center; font-size: 13px; font-style: italic;
            color: #7a7358; margin: 0.3in 0.75in 0 0.75in;
        }

        .fbl-b-album {
            /* padding-top (not margin-top) provides clearance from the slim
               running header on a continuation page: dompdf drops an
               element's top margin when it lands first on a new page
               (margin-collapsing across the break), but padding is never
               collapsed, so it's the only reliable way to reserve that
               28pt of space here. */
            page-break-inside: avoid; margin: 8pt 0.75in 0 0.75in;
            border-top: 1px solid #e0d9c4; padding-top: 40pt;
        }
        .fbl-b-album-heading { font-size: 13pt; font-weight: bold; margin: 0 0 10pt 0; color: #16283f; }
        .fbl-b-masonry { width: 100%; border-collapse: collapse; }
        .fbl-b-col { vertical-align: top; padding: 0 4pt; }
        .fbl-b-col img {
            width: 100%; height: auto; display: block;
            margin-bottom: 8pt; border: 1pt solid #e0d9c4;
        }

        .fbl-b-footer {
            width: 100%; margin-top: 0.3in; padding: 0.22in 0.75in 0.3in 0.75in;
            border-top: 1px solid #e0d9c4; border-collapse: collapse;
        }
        .fbl-b-footer .contact { font-size: 12.5px; line-height: 1.7; color: #16283f; vertical-align: middle; }
        .fbl-b-footer .contact .biz { font-weight: bold; font-size: 14px; display: block; margin-bottom: 2px; }
        .fbl-b-footer .qr { width: 0.95in; text-align: center; vertical-align: middle; }
        .fbl-b-footer .qr img { width: 0.85in; height: 0.85in; }
        .fbl-b-footer .qr .cap {
            display: block; font-size: 9px; text-transform: uppercase;
            letter-spacing: 0.05em; color: #7a7358; margin-top: 3px;
        }
    </style>
    </head>
    <body>

        <div class="fbl-b-header">
            <?php if ($logo_id): ?>
                <div class="fbl-b-logo">
                    <img src="<?php echo esc_attr(fbl_brochure_image_path($logo_id, 'medium')); ?>" alt="">
                </div>
            <?php endif; ?>
            <div class="fbl-b-eyebrow"><?php echo esc_html($copy['eyebrow']); ?></div>
            <h1 class="fbl-b-title"><?php echo esc_html($copy['title']); ?></h1>
            <div class="fbl-b-header-sub"><?php echo esc_html($copy['header_sub']); ?></div>
        </div>

        <div class="fbl-b-body">
            <p class="fbl-b-lede"><?php echo fbl_brochure_kses($copy['lede']); ?></p>

            <table class="fbl-b-highlights"><tr>
                <td class="fbl-b-highlight">
                    <span class="num"><?php echo esc_html($copy['stat1_num']); ?></span>
                    <span class="cap"><?php echo esc_html($copy['stat1_label']); ?></span>
                </td>
                <td class="fbl-b-highlight">
                    <span class="num"><?php echo esc_html($copy['stat2_num']); ?></span>
                    <span class="cap"><?php echo esc_html($copy['stat2_label']); ?></span>
                </td>
                <td class="fbl-b-highlight">
                    <span class="num"><?php echo esc_html($copy['stat3_num']); ?></span>
                    <span class="cap"><?php echo esc_html($copy['stat3_label']); ?></span>
                </td>
            </tr></table>

            <div class="fbl-b-species"><?php echo fbl_brochure_kses($copy['species_line']); ?></div>
        </div>

        <div class="fbl-b-cta">
            <div class="headline"><?php echo esc_html($copy['cta_headline']); ?></div>
            <div class="sub2"><?php echo esc_html($copy['cta_sub']); ?></div>
            <div class="url"><?php echo esc_html($copy['cta_url']); ?></div>
        </div>

        <div class="fbl-b-closing"><?php echo esc_html($copy['closing']); ?></div>

        <?php if ($has_album):
            $columns = 3;
            $cols    = fbl_brochure_masonry_columns($photo_ids, $columns);
            $col_pct = 100 / $columns;
        ?>
            <div class="fbl-b-album">
                <div class="fbl-b-album-heading">A Look Around Fish Buck Lake</div>
                <table class="fbl-b-masonry"><tr>
                    <?php foreach ($cols as $col_ids): ?>
                        <td class="fbl-b-col" style="width: <?php echo esc_attr($col_pct); ?>%;">
                            <?php foreach ($col_ids as $id):
                                $path = fbl_brochure_image_path($id);
                                if (!$path) continue;
                            ?>
                                <img src="<?php echo esc_attr($path); ?>" alt="">
                            <?php endforeach; ?>
                        </td>
                    <?php endforeach; ?>
                </tr></table>
            </div>
        <?php endif; ?>

        <table class="fbl-b-footer"><tr>
            <td class="contact">
                <span class="biz"><?php echo esc_html($copy['footer_business']); ?></span>
                <?php echo esc_html($copy['footer_address']); ?><br>
                <?php echo esc_html($copy['footer_phone']); ?> &nbsp;&bull;&nbsp; <?php echo esc_html($copy['footer_email']); ?>
            </td>
            <?php if ($qr_uri): ?>
                <td class="qr">
                    <img src="<?php echo esc_attr($qr_uri); ?>" alt="">
                    <span class="cap"><?php echo esc_html($copy['footer_qr_caption']); ?></span>
                </td>
            <?php endif; ?>
        </tr></table>

        <?php /* ---------------------------------------------------------
           Running header/footer - registered here, at the very end of the
           document, via dompdf's inline-PHP canvas hook, but actually drawn
           per page through Canvas::page_script(). Placement matters:
           page_script() loops over the canvas's pages *immediately* when
           called (it does not defer to a later render pass), so calling it
           any earlier - e.g. right after <body>, before pagination has
           happened - would only see page 1 and never fire for later pages.
           This is also the only way to tell page 1 apart from later pages;
           dompdf has no @page :first.

           The full hero masthead already covers page 1, and the full
           contact+QR footer above already covers the last page, so this
           only fills in the slim bar on pages in between - a no-op on the
           single-page (no album) brochure, since PAGE_NUM === PAGE_COUNT
           === 1 there, matching neither condition below.
           --------------------------------------------------------- */ ?>
        <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($PAGE_NUM, $PAGE_COUNT, $pdf, $fontMetrics) {
                $navy = array(0.086, 0.157, 0.247);
                $gray = array(0.478, 0.451, 0.267);

                $pw = $pdf->get_width();
                $ph = $pdf->get_height();

                $bold = $fontMetrics->getFont('DejaVu Sans', 'bold');
                $reg  = $fontMetrics->getFont('DejaVu Sans', 'normal');

                // Slim running header - every page after the first.
                if ($PAGE_NUM > 1) {
                    $pdf->filled_rectangle(0, 0, $pw, 28, $navy);
                    $title = <?php echo $running_title_literal; ?>;
                    $tw = $fontMetrics->getTextWidth($title, $bold, 11);
                    $pdf->text(($pw - $tw) / 2, 8, $title, $bold, 11, array(1, 1, 1));
                }

                // Slim running footer - every page except the last (the
                // last page already carries the full contact block + QR).
                if ($PAGE_NUM < $PAGE_COUNT) {
                    $pdf->line(54, $ph - 30, $pw - 54, $ph - 30, $gray, 0.5);
                    $contact = <?php echo $running_contact_literal; ?>;
                    $cw = $fontMetrics->getTextWidth($contact, $reg, 9);
                    $pdf->text(($pw - $cw) / 2, $ph - 24, $contact, $reg, 9, $navy);
                }
            });
        }
        </script>

    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->setChroot(array(get_stylesheet_directory(), wp_upload_dir()['basedir']));
    $options->setIsRemoteEnabled(false);
    // Needed for the running header/footer's <script type="text/php"> canvas
    // callback above. Safe here only because this HTML is entirely
    // self-generated by this file - no user-submitted markup ever reaches
    // loadHtml(), and the two copy fields interpolated into that script's
    // source are var_export()'d into safe string literals, not raw-echoed.
    $options->setIsPhpEnabled(true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $tmp_path = wp_tempnam('fbl-brochure.pdf');
    if (!$tmp_path) {
        return '';
    }

    file_put_contents($tmp_path, $dompdf->output());

    return $tmp_path;
}
