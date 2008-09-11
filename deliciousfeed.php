<!-- To customize this template, copy it to your currently active theme directory and edit it -->
<div id="deliciousfeed">
	<ul>
	<?php
		if (is_array($deliciousfeed)) {
			foreach ($deliciousfeed as $post) {
				printf('<li class="delicious-post"><a href="%1$s" title="%2$s">%3$s</a></li>', $post->url, $post->desc, $post->title);
			}
		} else // Exceptions
		{
			echo '<li class="delicious-error">' . $deliciousfeed . '</li>';
		}
	?>
	</ul>
</div>