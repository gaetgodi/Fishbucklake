<?php
/**
 * Template Name: Front Page
 * Description: Custom front-page template for Divi 5 migration
 */

get_header();

// Determine whether Divi Builder is used
$is_divi = function_exists('et_pb_is_pagebuilder_used') 
    ? et_pb_is_pagebuilder_used(get_the_ID()) 
    : false;
?>

<div id="main-content" class="fbl-frontpage">

    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

        <?php if ( $is_divi ) : ?>

            <!-- ⭐ Divi Builder Page Render -->
            <div class="et_builder_inner_content">
                <?php the_content(); ?>
            </div>

        <?php else : ?>

            <!-- ⭐ Fallback (no Divi builder used on this page) -->
            <div class="container fbl-fallback-wrapper">
                <h1 class="entry-title fbl-title"><?php the_title(); ?></h1>

                <div class="entry-content fbl-content">
                    <?php the_content(); ?>
                </div>
            </div>

        <?php endif; ?>

    <?php endwhile; endif; ?>

</div><!-- #main-content -->

<?php get_footer(); ?>
