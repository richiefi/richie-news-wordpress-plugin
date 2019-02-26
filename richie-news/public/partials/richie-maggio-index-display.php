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

<div class="maggio-container" style="display:flex;">
    <?php foreach( $issues as $issue ): ?>
        <div class="maggio-issue">
            <img src="<?php echo $issue->get_cover(200,300); ?>">
            <?php echo $issue->title ?>
        </div>
    <?php endforeach; ?>
</div>