<ul id="deliciousfeed">
	<?php if ( !isset( $content->error ) ): ?>
	<?php foreach ( $content->bookmarks as $post ): ?>
	<li class="delicious-post"><a href="<? echo $post->url; ?>" title="<?php echo Utils::htmlspecialchars( $post->desc ); ?>"><?php echo Utils::htmlspecialchars( $post->title ); ?></a></li>
	<?php endforeach; ?>
	<?php else: ?>
	<li class="delicious-error"><?php echo $content->error; ?></li>
	<?php endif; ?>
</ul>