<?php
/*
Template Name: Richie News Article template
*/
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <div id="content" class="site-content richie-content-wrapper">
        <div id="primary" class="content-area">
            <main id="main" class="site-main" role="main">
                <?php if ( have_posts() ) : ?>
                    <?php
                        // Load posts loop.
                        while ( have_posts() ) : the_post();
                            the_title( '<h1 class="entry-title">', '</h1>' );
                            the_content();
                        endwhile;
                    ?>
                <?php  endif; ?>
            </main>
        </div>
        <?php wp_footer(); ?>
    </div>
</body>
</html>