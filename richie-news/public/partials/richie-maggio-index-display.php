<?php

/**
 * Creates maggio issue list
 *
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie_News
 * @subpackage Richie_News/public/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<?php if ( !$user_has_access ): ?>
    <div style="text-align: center;">
        <strong>
            <?php _e('You need to be logged in and have an active order to access these magazines', $this->plugin_name); ?>
        </strong>
    </div>
<?php endif; ?>
<div class="maggio-container" style="display:flex;">
    <?php foreach( $issues as $issue ): ?>
        <div class="maggio-issue">
            <?php if( $user_has_access || $issue->is_free ): ?>
                <a href="<?php echo $issue->get_redirect_url(); ?>">
                    <img src="<?php echo $issue->get_cover(200,300); ?>">
                    <?php echo $issue->title ?>
                </a>
            <?php else: ?>
                <img src="<?php echo $issue->get_cover(200,300); ?>">
                <?php echo $issue->title ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>