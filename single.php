<?php
/**
 * CareerCompass Theme Single Post
 *
 * @package CareerCompass
 * @since 1.0.0
 */

get_header();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="cc-container" style="max-width: 800px; padding: var(--cc-spacing-12) var(--cc-spacing-6);">
		<header class="entry-header cc-mb-8">
			<?php if ( has_category() ) : ?>
				<div class="cc-mb-4">
					<?php
					$categories = get_the_category();
					if ( ! empty( $categories ) ) :
						echo '<span class="cc-tag">' . esc_html( $categories[0]->name ) . '</span>';
					endif;
					?>
					<span class="cc-ml-4 cc-text-neutral-500"><?php echo get_the_date(); ?></span>
				</div>
			<?php endif; ?>

			<h1 class="entry-title cc-text-gradient" style="font-size: var(--cc-font-size-4xl);">
				<?php the_title(); ?>
			</h1>

			<div class="entry-meta cc-mt-4" style="color: var(--cc-neutral-500);">
				<span><?php _e( '作者：', 'careercompass' ); ?> <?php the_author(); ?></span>
				<span class="cc-ml-4">·</span>
				<span class="cc-ml-4"><?php echo careercompass_reading_time(); ?></span>
			</div>
		</header>

		<div class="entry-content" style="line-height: var(--cc-line-height-relaxed);">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . __( '分页：', 'careercompass' ),
					'after'  => '</div>',
				)
			);
			?>
		</div>

		<footer class="entry-footer cc-mt-8" style="border-top: 1px solid var(--cc-neutral-200); padding-top: var(--cc-spacing-8);">
			<?php if ( has_tag() ) : ?>
				<div class="tags-links cc-mb-4">
					<?php
					$tags = get_the_tags();
					if ( $tags ) :
						foreach ( $tags as $tag ) :
							echo '<a href="' . esc_url( get_tag_link( $tag ) ) . '" class="cc-tag cc-mr-2">' . esc_html( $tag->name ) . '</a>';
						endforeach;
					endif;
					?>
				</div>
			<?php endif; ?>

			<nav class="post-navigation cc-mt-8">
				<div style="display: flex; justify-content: space-between;">
					<div>
						<?php previous_post_link( '%link', '<span style="color: var(--cc-neutral-500);">← ' . __( '上一篇', 'careercompass' ) . '</span><br><strong>%title</strong>' ); ?>
					</div>
					<div>
						<?php next_post_link( '%link', '<span style="color: var(--cc-neutral-500);">' . __( '下一篇', 'careercompass' ) . ' →</span><br><strong>%title</strong>' ); ?>
					</div>
				</div>
			</nav>
		</footer>

		<?php
		if ( comments_open() || get_comments_number() ) :
			comments_template();
		endif;
		?>
	</div>
</article>

<?php
get_footer();
